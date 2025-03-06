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
	private $raw_lexical_updates = array();

	public static function extract( string $filename, string $html ): array {
		$strings = array();
		$offsets = array();

		$processor  = new self( $html );
		$reflector  = new ReflectionClass( $processor );
		$attributes = $reflector->getParentClass()->getProperty( 'attributes' );

		while ( $processor->next_token() ) {
			$token_type = $processor->get_token_type();

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
				$token = self::next_translation_bit( $raw_text );
				if ( ! isset( $token ) || 0 !== $token['at'] ) {
					continue;
				}

				$context = '_x' === $token['func'] ? $token['args'][1] ?? '' : '';
				$domain  = (
					( '__' === $token['func'] ? $token['args'][1] ?? '' :
					( '_x' === $token['func'] ? $token['args'][2] ?? '' :
					( '_n' === $token['func'] ? $token['args'][3] ?? '' :
					'' ) ) )
				);

				$key = "{$token['funcall']}\x00{$token['args'][0]}\x00{$context}\x00{$domain}\x00{$token['note']}";

				if ( ! isset( $strings[ $key ] ) ) {
					$strings[ $key ] = array();
				}

				$strings[ $key ][] = $here->start + $token['at'];
				if ( 0 === count( $offsets ) || ( $here->start + $token['at'] ) !== $offsets[ count( $offsets ) - 1 ] ) {
					$offsets[] = $here->start + $token['at'];
				}
			}

			if ( '#tag' === $token_type ) {
				foreach ( $attributes->getValue( $processor ) ?? array() as $attribute ) {
					if ( $attribute->is_true ) {
						continue;
					}

					$raw_value = substr( $html, $attribute->value_starts_at, $attribute->value_length );
					$token     = self::next_translation_bit( $raw_value );
					if ( ! isset( $token ) ) {
						continue;
					}

					$context = '_x' === $token['func'] ? $token['args'][1] ?? '' : '';
					$domain  = (
						( '__' === $token['func'] ? $token['args'][1] ?? '' :
						( '_x' === $token['func'] ? $token['args'][2] ?? '' :
						( '_n' === $token['func'] ? $token['args'][3] ?? '' :
						'' ) ) )
					);

					$key = "{$token['funcall']}\x00{$token['args'][0]}\x00{$context}\x00{$domain}\x00{$token['note']}";

					if ( ! isset( $strings[ $key ] ) ) {
						$strings[ $key ] = array();
					}

					$strings[ $key ][] = $attribute->value_starts_at + $token['at'];
					if ( 0 === count( $offsets ) || ( $attribute->value_starts_at + $token['at'] ) !== $offsets[ count( $offsets ) - 1 ] ) {
						$offsets[] = $attribute->value_starts_at + $token['at'];
					}
				}
			}
		}

		$locations = self::offsets_to_location( $html, $offsets );
		$outputs   = array();

		foreach ( $strings as $key => $instances ) {
			list( $funcall, $first_arg, $context, $domain, $comment ) = explode( "\x00", $key );
			$outputs[ $first_arg ] = array_merge(
				empty( $comment ) ? array() : array( 'translator_comment' => $comment ),
				empty( $context ) ? array() : array( 'context' => $context ),
				empty( $domain ) ? array() : array( 'domain' => $domain ),
				array(
					'funcall' => $funcall,
					'locations' => array_map( fn ( $offset ) => array_merge( array( $filename ), $locations[ $offset ] ), $instances )
				)
			);
		}

		return $outputs;
	}

	public static function translate( string $html, Callable $translator ): string {
		$processor    = new self( $html );
		$reflector    = new ReflectionClass( $processor );
		$token_at     = $reflector->getParentClass()->getProperty( 'token_starts_at' );
		$token_length = $reflector->getParentClass()->getProperty( 'token_length' );
		$attributes   = $reflector->getParentClass()->getProperty( 'attributes' );

		while ( $processor->next_token() ) {
			$token_type = $processor->get_token_type();

			if ( ! in_array( $token_type, array( '#comment', '#tag' ), true ) ) {
				continue;
			}

			if ( '#comment' === $token_type && WP_HTML_Tag_Processor::COMMENT_AS_PI_NODE_LOOKALIKE !== $processor->get_comment_type() ) {
				continue;
			}

			$raw_text = substr( $processor->html, $token_at->getValue( $processor ), $token_length->getValue( $processor ) );

			// Ensure that it’s possible for a Translation Bit to appear here.
			if ( 1 !== preg_match( '~<?wp[^>]+>~', $raw_text ) ) {
				continue;
			}

			if ( '#comment' === $token_type ) {
				$token = self::next_translation_bit( $raw_text );
				if ( ! isset( $token ) || 0 !== $token['at'] ) {
					continue;
				}

				$processor->dangerously_replace_span(
					$token_at->getValue( $processor ) + $token['at'],
					$token['length'],
					$translator( $token )
				);
			}

			if ( '#tag' === $token_type ) {
				foreach ( $attributes->getValue( $processor ) ?? array() as $attribute ) {
					if ( $attribute->is_true ) {
						continue;
					}

					$raw_value = substr( $html, $attribute->value_starts_at, $attribute->value_length );
					$token     = self::next_translation_bit( $raw_value );
					if ( ! isset( $token ) ) {
						continue;
					}

					$processor->dangerously_replace_span(
						$attribute->value_starts_at + $token['at'],
						$token['length'],
						$translator( $token )
					);
				}
			}
		}

		$output_processor = new WP_HTML_Tag_Processor( $html );
		$output_processor->lexical_updates = $processor->raw_lexical_updates;
		return $output_processor->get_updated_html();
	}

	private function dangerously_replace_span( int $at, int $length, string $new_html ): bool {
		$this->raw_lexical_updates[] = new WP_HTML_Text_Replacement(
			$at,
			$length,
			WP_HTML_Processor::normalize( $new_html )
		);

		return true;
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

		foreach ( $strings as $string => $info ) {
			$location_header = '#:';

			if ( ! empty( $info['translator_comment'] ) ) {
				$fragment .= "#. {$info['translator_comment']}\n\n";
			}

			$context = ! empty( $info['context'] ) ? str_replace( '"', '\\"', $info['context'] ) : null;

			foreach ( $info['locations'] as $location ) {
				list( $filename, $line, $column ) = $location;
				$location_header .= " {$filename}:{$line}";
			}

			$quoted_string = str_replace( '"', '\"', $string );

			$fragment .= str_replace(
				"msgctxt \"\"\n",
				'',
				<<<MSG
				{$location_header}
				msgctxt "{$context}"
				msgid "{$quoted_string}"
				msgstr ""


				MSG
			);
		}

		return $fragment;
	}

	private static function next_translation_bit( string $text ): ?array {
		$pattern = "~<\?wp[ \\t\\f\\r\\n]+(?P<NOTE>\\/\\*((?!\\*\\/)[^>])*\\*\\/[ \\t\\f\\r\\n]+)?(?P<FUNCALL>(?P<FUN>_[_nx])\\([ \\t\\f\\r\\n]*(?P<ARGS>(['\"])(\\\\\\6|((?!\\6).))*\\6[ \\t\\f\\r\\n]*(?:,[ \\t\\f\\r\\n]*(['\"])(\\\\\\9|((?!\\9).))*\\9[ \\t\\f\\r\\n]*)*)\\);)[ \\t\\f\\r\\n]+\\?>~m";
		if ( 1 !== preg_match( $pattern, $text, $token_match, PREG_OFFSET_CAPTURE ) ) {
			echo "\e[31mNo match\e[m for \e[35m{$text}\e[m\n";
			return null;
		}

		$args        = $token_match['ARGS'][0];
		$args_array  = array();
		$first_quote = $args[0];
		$at          = 1;
		while ( $at < strlen( $args ) ) {
			$next_quote = strpos( $args, $first_quote, $at );
			if ( false === $next_quote ) {
				break;
			}

			if ( $next_quote > $first_quote && '\\' === $args[ $next_quote - 1 ] ) {
				$at = $next_quote + 1;
				continue;
			}

			$arg          = substr( $args, $at, $next_quote - $at );
			$arg          = str_replace( "\\{$first_quote}", $first_quote, $arg );
			$args_array[] = $arg;

			$at = $next_quote + 1;
			if ( $at < strlen( $args ) ) {
				$at += strspn( $args, ", \t\f\r\n", $at );
			}
			if ( $at >= strlen( $args ) ) {
				break;
			}

			$first_quote = $args[ $at++ ];
		}

		return array(
			'at'      => $token_match[0][1],
			'length'  => strlen( $token_match[0][0] ),
			'note'    => trim( substr( $token_match['NOTE'][0], 2, strlen( trim( $token_match['NOTE'][0], " \t\f\r\n" ) ) - 4 ), " \t\f\r\n" ),
			'funcall' => $token_match['FUNCALL'][0],
			'func'    => $token_match['FUN'][0],
			'args'    => $args_array,
		);
	}
}
