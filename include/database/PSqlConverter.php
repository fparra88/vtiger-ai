<?php

/**
 * Converts a literal SQL string (SELECT/INSERT/UPDATE/DELETE/etc.) into a
 * parameterized query + bound values, by tokenizing the SQL and replacing
 * string/number literals with '?' placeholders.
 */
final class PSqlConverter
{
    /**
     * @return array{sql: string, values: array<int, mixed>}
     */
    public static function convert(string $sql): array
    {
        // Delimiter is '~' (not '/') because the block-comment alternative
        // contains literal '/' characters, which would otherwise collide
        // with a '/'-delimited pattern.
        //
        // NOTE: single-/double-quoted strings and backtick-quoted
        // identifiers are deliberately NOT matched by this regex (see
        // scanQuotedLiteral() below for why) - the pattern below only
        // covers every other token kind.
        static $pattern = <<<'REGEX'
~
    \G(?:
          (?P<ws>[ \t\r\n]+)
        | (?P<linecomment>(?:--|\#)[^\n]*)
        | (?P<blockcomment>/\*[^*]*\*+(?:[^/*][^*]*\*+)*/)
        | (?P<variable>@{1,2}[A-Za-z0-9_$.]*)
        | (?P<hexnum>0x[0-9A-Fa-f]+)
        | (?P<num>(?:\d+\.\d+|\.\d+|\d+)(?:[eE][+-]?\d+)?)
        | (?P<placeholder>\?)
        | (?P<op><=>|<>|<=|>=|!=|:=|->>|->|<<|>>|&&|\|\|)
        | (?P<punct>[=<>+\-*/%&|^\~!(),;.])
        | (?P<ident>[A-Za-z_][A-Za-z0-9_$]*)
    )
~xs
REGEX;

        $index        = 0;
        $length       = strlen($sql);
        $values       = [];
        $preparedSql  = '';
        $sawSemicolon = false;

        while ($index < $length) {
            $ch = $sql[$index];

            // --- Quoted literal (string or backtick identifier) ---
            if ($ch === "'" || $ch === '"' || $ch === '`') {
                $end   = self::scanQuotedLiteral($sql, $index, $ch);
                $token = substr($sql, $index, $end - $index);

                if ($ch === '`') {
                    // Backtick-quoted identifier: pass through unchanged.
                    $preparedSql .= $token;
                } else {
                    // Assumes default MySQL mode (ANSI_QUOTES off) for
                    // double-quoted strings, where "..." is a string
                    // literal, not a quoted identifier. Under ANSI_QUOTES,
                    // double quotes mean identifiers instead - this branch
                    // would need to move to the backtick case above.
                    $values[] = self::decodeStringLiteral(substr($token, 1, -1), $ch);
                    $preparedSql .= '?';
                }

                $index = $end;
                continue;
            }

            if (!preg_match($pattern, $sql, $m, PREG_UNMATCHED_AS_NULL, $index)) {
                throw new Exception(sprintf(
                    'Unexpected input at byte offset %d near: %s',
                    $index,
                    substr($sql, $index, 20)
                ));
            }

            $token    = $m[0];
            $tokenLen = strlen($token);

            // --- Whitespace / comments: copy through verbatim ---
            if ($m['ws'] !== null || $m['linecomment'] !== null || $m['blockcomment'] !== null) {
                $preparedSql .= $token;
                $index += $tokenLen;
                continue;
            }

            // --- User/session variable (@var, @@global_var): pass through ---
            if ($m['variable'] !== null) {
                $preparedSql .= $token;
                $index += $tokenLen;
                continue;
            }

            // --- Hex literal -> integer value ---
            if ($m['hexnum'] !== null) {
                $values[] = hexdec(substr($token, 2));
                $preparedSql .= '?';
                $index += $tokenLen;
                continue;
            }

            // --- Numeric literal (int/decimal/float, unsigned) -> value ---
            // Deliberately never absorbs a leading +/- sign: the sign is
            // tokenized separately as an operator. This removes the
            // "5-3 silently mis-tokenizes as 5, -3" ambiguity from the
            // previous version entirely, since '-' is always its own token
            // and MySQL is happy to apply unary minus to a placeholder
            // (`- ?` is valid syntax).
            if ($m['num'] !== null) {
                $values[] = self::numericValue($token);
                $preparedSql .= '?';
                $index += $tokenLen;
                continue;
            }

            // --- Pre-existing '?' in input: reject ---
            // We can't safely support mixing caller-supplied placeholders
            // with ones we generate: positional order would be ambiguous
            // (we'd have no value for the caller's placeholder, silently
            // shifting every subsequent binding by one). Input must be
            // fully literal SQL.
            if ($m['placeholder'] !== null) {
                throw new Exception(
                    'Input SQL already contains a "?" placeholder at byte offset ' .
                    $index . '; this function expects fully-literal SQL as input.'
                );
            }

            // --- Multi-char / single-char operators and punctuation ---
            if ($m['op'] !== null || $m['punct'] !== null) {
                if ($token === ';') {
                    if ($sawSemicolon) {
                        throw new Exception(
                            'Multiple statements are not supported (found a second ";" ' .
                            'at byte offset ' . $index . ').'
                        );
                    }
                    $sawSemicolon = true;
                }
                $preparedSql .= $token;
                $index += $tokenLen;
                continue;
            }

            // --- Identifier / keyword: pass through unchanged ---
            // This includes NULL, TRUE, FALSE, DEFAULT, function names,
            // column/table names, AND/OR/IN/LIKE, etc. NULL/TRUE/FALSE are
            // deliberately NOT turned into placeholders: `col IS ?` is
            // invalid MySQL syntax (IS only accepts the literals NULL,
            // TRUE, FALSE, UNKNOWN), so parameterizing them would require
            // fragile lookback logic to detect "preceded by IS/IS NOT" and
            // still risk edge cases. Leaving them as literal keywords is
            // simpler and always syntactically correct.
            $preparedSql .= $token;
            $index += $tokenLen;
        }

        if ($sawSemicolon) {
            $semicolonPos = strrpos($sql, ';');
            if ($semicolonPos !== false && trim(substr($sql, $semicolonPos + 1)) !== '') {
                throw new Exception(
                    'Content found after terminating ";" - stacked statements are not supported.'
                );
            }
        }

        return [
            'sql'    => $preparedSql,
            'values' => $values,
        ];
    }

    /**
     * Finds the end of a quoted literal (string or backtick identifier)
     * starting at $sql[$start] (which must be $quoteChar), by scanning
     * forward one byte at a time - no regex involved. Returns the index
     * one past the closing quote. Handles both ways a quote character can
     * appear inside the literal without ending it: a doubled quote ('' or
     * ``) and a backslash-escaped character (\X, for any X) - the same two
     * cases decodeStringLiteral() decodes afterward.
     */
    private static function scanQuotedLiteral(string $sql, int $start, string $quoteChar): int
    {
        $length = strlen($sql);
        $i = $start + 1;

        while ($i < $length) {
            $ch = $sql[$i];

            if ($ch === $quoteChar) {
                if ($i + 1 < $length && $sql[$i + 1] === $quoteChar) {
                    $i += 2; // doubled quote - still inside the literal
                    continue;
                }
                return $i + 1; // the true closing quote
            }

            // Backslash escapes only apply inside '...' and "..." strings,
            // not backtick identifiers (MySQL backtick-quoting only
            // recognizes the doubled-backtick escape, not backslash).
            if ($quoteChar !== '`' && $ch === '\\' && $i + 1 < $length) {
                $i += 2;
                continue;
            }

            $i++;
        }

        throw new RuntimeException(
            'Unterminated string literal starting at byte offset ' . $start
        );
    }

    /**
     * Decodes a SQL string literal's contents, handling BOTH escaping
     * styles MySQL may produce/accept: doubled-quote escaping ('' -> ')
     * and backslash escaping (\n, \t, \', \\, etc. per MySQL's default
     * sql_mode, i.e. NO_BACKSLASH_ESCAPES is not set).
     *
     * If your data was written with NO_BACKSLASH_ESCAPES enabled, backslash
     * has no special meaning and this function should be adjusted (drop
     * the backslash branch) - documented here since it's a real ambiguity
     * source if you don't know your sql_mode.
     */
    private static function decodeStringLiteral(string $inner, string $quoteChar): string
    {
        $out = '';
        $len = strlen($inner);

        for ($i = 0; $i < $len; $i++) {
            $ch = $inner[$i];

            if ($ch === $quoteChar && ($inner[$i + 1] ?? '') === $quoteChar) {
                $out .= $quoteChar;
                $i++;
                continue;
            }

            if ($ch === '\\' && $i + 1 < $len) {
                $next = $inner[$i + 1];
                switch ($next) {
                    case '0':  $out .= "\0"; break;
                    case "'":  $out .= "'"; break;
                    case '"':  $out .= '"'; break;
                    case 'b':  $out .= "\x08"; break;
                    case 'n':  $out .= "\n"; break;
                    case 'r':  $out .= "\r"; break;
                    case 't':  $out .= "\t"; break;
                    case 'Z':  $out .= "\x1a"; break;
                    case '\\': $out .= '\\'; break;
                    // \% and \_ retain the backslash outside LIKE patterns
                    // per MySQL docs; everything else unrecognized just
                    // drops the backslash and keeps the char literally.
                    case '%':  $out .= '\%'; break;
                    case '_':  $out .= '\_'; break;
                    default:   $out .= $next;
                }
                $i++;
                continue;
            }

            $out .= $ch;
        }

        return $out;
    }

    /**
     * Converts a numeric literal token to int, float, or (for integers
     * outside PHP's native int range, e.g. large BIGINT UNSIGNED values)
     * a numeric string, to avoid silent precision loss.
     */
    private static function numericValue(string $numText): int|float|string
    {
        if (strpbrk($numText, '.eE') !== false) {
            return (float) $numText;
        }

        // Integer: guard against overflow past PHP_INT_MAX.
        $digits = ltrim($numText, '0');
        if ($digits === '') {
            return 0;
        }

        $maxDigits = strlen((string) PHP_INT_MAX);
        if (strlen($digits) < $maxDigits
            || (strlen($digits) === $maxDigits && $digits <= (string) PHP_INT_MAX)
        ) {
            return (int) $numText;
        }

        // Too large for a native int - keep as numeric string; PDO/mysqli
        // will bind it fine as a string parameter for a BIGINT column.
        return $numText;
    }
}
