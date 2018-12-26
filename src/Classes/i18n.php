<?php
/**
 * Implement the I18n class and some useful functions.
 * Slightly modified version where the functions names are replaced
 * so the .po file generator from ICanLocalize can be used.
 *
 * @author		davidesper
 * @copyright	23-Jan-2010
 * @link		http://www.php-hispano.net/archivos/Clases/323/1
 * @package		ProjectSend
 * @subpackage	Classes
 *
 */

namespace ProjectSend\Classes;

/**
 * Define this constant to be use as the default domain
 * using in auxilar functions around {@link I18n} class.
 *
 */
if( ! defined( 'I18N_DEFAULT_DOMAIN' ) ) {
  define( 'I18N_DEFAULT_DOMAIN', '' );
}

/**
 * Useful class to deal with I18n and L10n
 *
 * This class is enterily taken from Habari project and also have
 * code writen (I think) by Danilo Segan <danilo@kvota.net>.
 *
 * Use this class to load text domain to be availables, then use
 * the auxiliar functions (implemented above) to work.
 *
 */
class I18n
{
  /**#@+
   * @static
   * @access private
   */

  /**
   * @var Array Loaded text domains and messages
   *
   */
  private static $messages = array();

  /**
   * @var Array Loaded MO files
   *
   */
  private static $moFiles = array();

  /**
   * @see ParseFile()
   *
   * @var String Plural function ID to be used
   *
   */
  private static $pluralFunc = array();

  /**#@-*/

  /**#@+
   * @final
   * @access private
   */

  /**
   * Disable object instances
   *
   */
  private final function  __construct(){}

  /**
   * Disable object clones
   *
   */
  private final function  __clone(){}

  /**#@-*/

  /**#@+
   * @access public
   */

  /**
   * Load a internationalization text domain
   *
   * You need to call this method in order to put messages
   * of a MO files availables to be use.
   *
   * The text domain using here is that you need to use
   * when translate strings.
   *
   * More than one domain text (and MO files) can be loaded.
   *
   * @param String $moFile MO file path to be loaded
   * @param String $domain Text domain to store the messages
   * @return Boolean True on success or False on failure
   * 
   */
  public static function LoadDomain( $moFile, $domain )
  {
    return self::LoadFile( $moFile, $domain );
  }

  /**
   * Get a translated string
   *
   * Of course you can use this method but look the above implemented
   * function {@link t()} that finally call this method for you.
   *
   * In other words, the existence of {@link t()} have sense because
   * the identifier is tiny and fast, ideal to a profuse use.
   *
   * @param String $string To be translate
   * @param String $domain Text domain where find
   * @return Strint Translated string if possible
   *
   */
	public static function Translate( $string, $domain )
  {
		if( isset( self::$messages[ $domain ][ $string ] ) )
    {
			$string = self::$messages[ $domain ][ $string ][1][0];
		}

		return $string;
	}

/**
 * Get a translated singular or plural string
 *
 * Same as {@link Translate()}, you find more useful to use
 * this method from {@link n()} function.
 *
 * @param String $singular Singular string version
 * @param String $plural Plural string version
 * @param Integer $count Determine what string version use
 * @param String $domain String domain text
 * @return String Translated string if possible
 *
 */
	public static function NTranslate( $singular, $plural, $count, $domain )
	{
		if( isset( self::$messages[ $domain ][ $singular ] ) )
    {
			$fn = self::$pluralFunc;
      
			$n = $fn( $count );

			if( isset( self::$messages[ $domain ][ $singular ][1][ $n ] ) )
      {
				return self::$messages[ $domain ][ $singular ][1][ $n ];
			}
		}
		/** fall-through else for both cases */
		return $count == 1 ? $singular : $plural;
	}

  /**#@-*/

  /**#@+
   * @access private
   */

  /**
   * Load MO files
   *
   * @param String $moPath MO file path to be loaded
   * @param String $domain Text domain messages to set
   * @return Boolean True on success or False on failure
   *
   */
  private static function LoadFile( $moPath, $domain )
  {
    if( in_array( $moPath, self::$moFiles ) )
    {
      return true;
    }
    else
    {
      if( self::ParseFile( $moPath, $domain ) )
      {
        self::$moFiles[] = $moPath;

        return true;
      }
      else
      {
        return false;
      }
    }
  }

  /**
   * Parse MO files
   *
   * I think originally writen to PHP-gettext by Danilo Segan
   * <danilo@kvota.net>.
   *
   * @param String $moPath Absolute MO file path
   * @param String $domain Text domain messages to set
   * @return Boolean True on success or False on failure
   *
   */
	private static function ParseFile( $moPath, $domain )
  {
    if( ! is_readable( $moPath ) )
    {
			return false;
		}
		if( filesize( $moPath ) < 24 )
    {
		  /** Invalid .MO file */
			return false;
		}

		$fp = fopen( $moPath, 'rb' );
		$data = fread( $fp, filesize( $moPath ) );
		fclose( $fp );

		/** Determine endianness */
		$littleEndian = true;
		
    list( , $magic )= unpack( 'V1', substr( $data, 0, 4 ) );

		switch ( $magic & 0xFFFFFFFF )
    {
		  case (int)0x950412de:
        $littleEndian = true;
				break;

			case (int)0xde120495:
			  $littleEndian = false;
				break;

			default:
			  /** Invalid magic number */
			  return false;
		}

		$revision = substr( $data, 4, 4 );
		if( $revision != 0 ) {
			/** Unknown revision number */
			return false;
		}

		$l = $littleEndian ? 'V' : 'N';

		if( $data && strlen( $data ) >= 20 )
    {
		  $header = substr( $data, 8, 12 );
			$header = unpack( "{$l}1msgcount/{$l}1msgblock/{$l}1transblock", $header );

			if($header['msgblock'] + ($header['msgcount'] - 1) * 8 > filesize($moPath))
      {
			  /** Message count out of bounds */
        return false;
			}

			$lo = "{$l}1length/{$l}1offset";

			for( $msgindex = 0; $msgindex < $header[ 'msgcount' ]; $msgindex++ )
      {
			  $msginfo = unpack
        (
          $lo, substr( $data, $header[ 'msgblock' ] + $msgindex * 8, 8 )
        );

				$msgids = explode
        (
          '\0', substr( $data, $msginfo[ 'offset' ], $msginfo[ 'length' ] )
        );

				$transinfo = unpack
        (
          $lo, substr( $data, $header[ 'transblock' ] + $msgindex * 8, 8 )
        );

				$transids = explode
        ( 
          '\0', substr( $data, $transinfo[ 'offset' ], $transinfo[ 'length' ] )
        );

				self::$messages[ $domain ][ $msgids[0] ] = array( $msgids, $transids );
			}
		}

		self::$pluralFunc = self::GetPluralFunc
    (
      self::$messages[ $domain ][''][1][0]
    );

    return 
      isset( self::$messages[ $domain ] )
        && count( self::$messages[ $domain ] ) > 0;
	}

  /**
   * Appropiate plural function from a MO file header
   *
   * Completly taken from Habari project <http://habariproject.org/>.
   *
   * @see ParseFile()
   * @return Unique function name as a string or False on error
   *
   */
	private static function GetPluralFunc( $moHeader )
	{
		if
    (
      preg_match
      (
        '/plural-forms: (.*?)$/i', $moHeader, $matches
      )
      && preg_match
         (
           '/^\s*nplurals\s*=\s*(\d+)\s*;\s*plural=(.*)$/', 
           $matches[1],
           $matches
         )
    )
    {
			/** sanitize */
			$nplurals = preg_replace( '/[^0-9]/', '', $matches[1] );

			$plural = preg_replace
      (
        '/[^n0-9:\(\)\?\|\&=!<>+*/\%-]/', '', $matches[2]
      );

			$body = str_replace
      (
				array( 'plural',  'n',  '$n$plurals', ),
				array( '$plural', '$n', '$nplurals', ),
        "nplurals={$nplurals}; plural={$plural}"
			);

			/** Add parens (important since PHP's ternary evaluates from left to right) */
			$body .= ';';

			$res = '';

			$p = 0;

			for ( $i = 0; $i < strlen( $body ); $i++ )
      {
				$ch = $body[$i];
        
				switch ( $ch )
        {
					case '?':
						$res.= ' ? (';
						$p++;
						break;

					case ':':
						$res.= ') : (';
						break;

					case ';':
						$res.= str_repeat( ')', $p) . ';';
						$p = 0;
						break;

					default:
						$res.= $ch;
				}
			}

			$body = $res . 'return ( $plural >= $nplurals ? $nplurals - 1: $plural );';
      
			$fn = create_function( '$n', $body );
		}
		else
    {
		/**
		 * default: one plural form for all cases
		 * but n==1 (english and spanish for example)
		 * http://www.gnu.org/software/gettext/manual/html_node/Plural-forms.html#Plural-forms
		 */
			$fn = create_function
      (
				'$n',
				'$nplurals=2; 
         $plural = ( $n == 1 ? 0 : 1 );
         return ( $plural >= $nplurals ? $nplurals - 1 : $plural );'
			);
		}

		return $fn;
	}

  /**#@-*/
}