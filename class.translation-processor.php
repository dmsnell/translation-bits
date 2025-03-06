<?php

/**
 * Translation_Processor class.
 *
 * ## Todo
 *   - Add support for translator comment extraction.
 *   - Parse more complicated translation calls than __() with no parameters.
 *   - Add translate function for render.
 */
class Translation_Processor extends WP_HTML_Tag_Processor {
	public static function extract( string $filename, string $html ): array {
		$strings = array();
		$offsets = array();

		$processor = new self( $html );

		while ( $processor->next_token() ) {
			$token_type = $processor->get_token_type();
			$token_name = $processor->get_token_name();

			if ( ! in_array( $token_type, array( '#comment', '#tag' ), true ) ) {
				continue;
			}

			if ( '#comment' === $token_type && WP_HTML_Tag_Processor::COMMENT_AS_PI_NODE_LOOKALIKE !== $processor->get_comment_type() ) {
				continue;
			}

			$processor->set_bookmark( 'here' );
			$here = $processor->bookmarks['here'];
			$raw_text = substr( $processor->html, $here->start, $here->length );

			// Ensure that it’s possible for a Translation Bit to appear here.
			if ( 1 !== preg_match( '~<?wp[^>]+>~', $raw_text ) ) {
				continue;
			}

			if ( '#comment' === $token_type ) {
				$translation_text = self::parse_translation_text( substr( $raw_text, 4, -2 ) );
				if ( ! isset( $translation_text ) ) {
					continue;
				}

				list( $string ) = $translation_text;
				if ( ! isset( $strings[ $string ] ) ) {
					$strings[ $string ] = array();
				}

				$strings[ $string ][] = $here->start;
				if ( 0 === count( $offsets ) || $here->start !== $offsets[ count( $offsets ) - 1 ] ) {
					$offsets[] = $here->start;
				}
			}
		}

		$locations = self::offsets_to_location( $html, $offsets );
		foreach ( $strings as $string => $instances ) {
			$strings[ $string ] = array_map( fn ( $offset ) => array_merge( array( $filename ), $locations[ $offset ] ), $instances );
		}

		return $strings;
	}

	public static function offsets_to_location( string $text, array $offsets ): array {
		$end            = strlen( $text );
		$line_number    = 1;
		$line_starts_at = 0;

		$offset_at    = 0;
		$offset_count = count( $offsets );
		$locations    = array();

		do {
			/*
			 * Like HTML, count all CR LF grapheme clusters as a newline,
			 * then treat all lone CR as an LF, and treat every remaining
			 * LF as a newline.
			 */
			$line_ends_at = $line_starts_at + strcspn( $text, "\r\n", $line_starts_at );

			while ( $offset_at < $offset_count ) {
				$offset = $offsets[ $offset_at ];
				if ( $offset >= $line_ends_at ) {
					break;
				}

				$locations[ $offset ] = array( $line_number, $offset - $line_starts_at + 1 );
				$offset_at++;
			}

			if ( $line_ends_at >= $end ) {
				break;
			}

			$line_number++;
			$line_starts_at = $line_ends_at;
			$line_end       = $text[ $line_ends_at ];
			if ( "\n" === $line_end ) {
				$line_starts_at++;
			} else if ( $line_ends_at + 2 < $end && "\r" === $line_end && "\n" === $text[ $line_ends_at + 1 ] ) {
				$line_starts_at += 2;
			} else {
				$line_starts_at++;
			}
		} while ( $line_starts_at < $end );

		return $locations;
	}

	public static function strings_to_pot_fragment( array $strings ): string {
		$fragment = '';

		foreach ( $strings as $string => $locations ) {
			$location_header = '#:';

			foreach ( $locations as $location ) {
				list( $filename, $line, $column ) = $location;
				$location_header .= " {$filename}:{$line}";
			}

			$quoted_string = str_replace( '"', '\"', $string );

			$fragment .= <<<MSG
			{$location_header}
			msgid "{$quoted_string}"
			msgstr ""


			MSG;
		}

		return $fragment;
	}

	public static function parse_translation_text( string $text ): ?array {
		// Skip leading whitespace.
		$at = strspn( $text, " \t\f\r\n" );

		if ( 0 !== substr_compare( $text, '__(', $at, 3 ) ) {
			echo "Doesn’t start a translation\n";
			return null;
		}

		$at += 3 + strspn( $text, " \t\f\r\n", $at + 3 );
		if ( 1 !== strspn( $text[ $at ], "'\"", 0, 1 ) ) {
			return null;
		}

		$quote_at = $at;
		$quote    = $text[ $at ];
		$end      = $at + strcspn( $text, $quote, $at + 1 ) + 1;

		$at = $end + 1 + strspn( $text, " \t\f\r\n", $end + 1 );
		if ( 0 !== substr_compare( $text, ');', $at, 2 ) ) {
			return null;
		}

		$at += 2 + strspn( $text, " \t\f\r\n", $at + 2 );
		if ( $at !== strlen( $text ) ) {
			echo "{$text[ $at ]}\n";
			return null;
		}

		return array( substr( $text, $quote_at + 1, $end - $quote_at - 1 ) );
	}
}
