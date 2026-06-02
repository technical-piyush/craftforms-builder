<?php
/**
 * Country list for the country field type.
 *
 * @package CraftFormsBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Country lists for country and phone field types.
 */
final class CFB_Countries {

	/**
	 * Country option values for select fields.
	 *
	 * @return string[] Country names keyed by sanitized value.
	 */
	public static function get_options() {
		$names = self::get_names();

		/**
		 * Filter country options shown in the country dropdown field.
		 *
		 * @param string[] $names Country display names.
		 */
		$names = apply_filters( 'cfb_country_options', $names );

		return array_values( $names );
	}

	/**
	 * Countries with ISO and dial codes for phone fields.
	 *
	 * @return array<int, array{name:string,iso:string,dial:string}>
	 */
	public static function get_phone_countries() {
		$map   = self::get_dial_map();
		$items = array();

		foreach ( self::get_names() as $name ) {
			if ( empty( $map[ $name ] ) ) {
				continue;
			}
			$items[] = array(
				'name' => $name,
				'iso'  => $map[ $name ]['iso'],
				'dial' => $map[ $name ]['dial'],
			);
		}

		/**
		 * Filter phone country metadata (name, ISO 3166-1 alpha-2, dial code without +).
		 *
		 * @param array<int, array{name:string,iso:string,dial:string}> $items Countries.
		 */
		return apply_filters( 'cfb_phone_countries', $items );
	}

	/**
	 * Resolve ISO code from a country display name.
	 *
	 * @param string $name Country name.
	 * @return string ISO 3166-1 alpha-2 or empty string.
	 */
	public static function get_iso_by_name( $name ) {
		$name = trim( (string) $name );
		if ( '' === $name ) {
			return '';
		}

		$map = self::get_dial_map();
		if ( isset( $map[ $name ]['iso'] ) ) {
			return $map[ $name ]['iso'];
		}

		return '';
	}

	/**
	 * Resolve dial code (digits only) from a country display name.
	 *
	 * @param string $name Country name.
	 * @return string Dial code without +.
	 */
	public static function get_dial_by_name( $name ) {
		$name = trim( (string) $name );
		if ( '' === $name ) {
			return '';
		}

		$map = self::get_dial_map();
		if ( isset( $map[ $name ]['dial'] ) ) {
			return (string) $map[ $name ]['dial'];
		}

		return '';
	}

	/**
	 * Default phone country ISO from site locale (falls back to US).
	 *
	 * @return string Two-letter ISO code.
	 */
	public static function get_default_phone_iso() {
		$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
		$parts  = explode( '_', (string) $locale );
		$iso    = strtoupper( isset( $parts[1] ) ? $parts[1] : 'US' );

		if ( strlen( $iso ) !== 2 ) {
			$iso = 'US';
		}

		/**
		 * Filter default country ISO for new phone fields.
		 *
		 * @param string $iso ISO 3166-1 alpha-2.
		 */
		return apply_filters( 'cfb_default_phone_country_iso', $iso );
	}

	/**
	 * Map country display names to ISO and dial codes.
	 *
	 * @return array<string, array{iso:string,dial:string}>
	 */
	private static function get_dial_map() {
		return array(
			'Afghanistan'            => array(
				'iso'  => 'AF',
				'dial' => '93',
			),
			'Albania'                => array(
				'iso'  => 'AL',
				'dial' => '355',
			),
			'Algeria'                => array(
				'iso'  => 'DZ',
				'dial' => '213',
			),
			'Andorra'                => array(
				'iso'  => 'AD',
				'dial' => '376',
			),
			'Angola'                 => array(
				'iso'  => 'AO',
				'dial' => '244',
			),
			'Argentina'              => array(
				'iso'  => 'AR',
				'dial' => '54',
			),
			'Armenia'                => array(
				'iso'  => 'AM',
				'dial' => '374',
			),
			'Australia'              => array(
				'iso'  => 'AU',
				'dial' => '61',
			),
			'Austria'                => array(
				'iso'  => 'AT',
				'dial' => '43',
			),
			'Azerbaijan'             => array(
				'iso'  => 'AZ',
				'dial' => '994',
			),
			'Bahrain'                => array(
				'iso'  => 'BH',
				'dial' => '973',
			),
			'Bangladesh'             => array(
				'iso'  => 'BD',
				'dial' => '880',
			),
			'Belarus'                => array(
				'iso'  => 'BY',
				'dial' => '375',
			),
			'Belgium'                => array(
				'iso'  => 'BE',
				'dial' => '32',
			),
			'Belize'                 => array(
				'iso'  => 'BZ',
				'dial' => '501',
			),
			'Benin'                  => array(
				'iso'  => 'BJ',
				'dial' => '229',
			),
			'Bhutan'                 => array(
				'iso'  => 'BT',
				'dial' => '975',
			),
			'Bolivia'                => array(
				'iso'  => 'BO',
				'dial' => '591',
			),
			'Bosnia and Herzegovina' => array(
				'iso'  => 'BA',
				'dial' => '387',
			),
			'Botswana'               => array(
				'iso'  => 'BW',
				'dial' => '267',
			),
			'Brazil'                 => array(
				'iso'  => 'BR',
				'dial' => '55',
			),
			'Brunei'                 => array(
				'iso'  => 'BN',
				'dial' => '673',
			),
			'Bulgaria'               => array(
				'iso'  => 'BG',
				'dial' => '359',
			),
			'Cambodia'               => array(
				'iso'  => 'KH',
				'dial' => '855',
			),
			'Cameroon'               => array(
				'iso'  => 'CM',
				'dial' => '237',
			),
			'Canada'                 => array(
				'iso'  => 'CA',
				'dial' => '1',
			),
			'Chile'                  => array(
				'iso'  => 'CL',
				'dial' => '56',
			),
			'China'                  => array(
				'iso'  => 'CN',
				'dial' => '86',
			),
			'Colombia'               => array(
				'iso'  => 'CO',
				'dial' => '57',
			),
			'Costa Rica'             => array(
				'iso'  => 'CR',
				'dial' => '506',
			),
			'Croatia'                => array(
				'iso'  => 'HR',
				'dial' => '385',
			),
			'Cuba'                   => array(
				'iso'  => 'CU',
				'dial' => '53',
			),
			'Cyprus'                 => array(
				'iso'  => 'CY',
				'dial' => '357',
			),
			'Czech Republic'         => array(
				'iso'  => 'CZ',
				'dial' => '420',
			),
			'Denmark'                => array(
				'iso'  => 'DK',
				'dial' => '45',
			),
			'Dominican Republic'     => array(
				'iso'  => 'DO',
				'dial' => '1',
			),
			'Ecuador'                => array(
				'iso'  => 'EC',
				'dial' => '593',
			),
			'Egypt'                  => array(
				'iso'  => 'EG',
				'dial' => '20',
			),
			'El Salvador'            => array(
				'iso'  => 'SV',
				'dial' => '503',
			),
			'Estonia'                => array(
				'iso'  => 'EE',
				'dial' => '372',
			),
			'Ethiopia'               => array(
				'iso'  => 'ET',
				'dial' => '251',
			),
			'Finland'                => array(
				'iso'  => 'FI',
				'dial' => '358',
			),
			'France'                 => array(
				'iso'  => 'FR',
				'dial' => '33',
			),
			'Georgia'                => array(
				'iso'  => 'GE',
				'dial' => '995',
			),
			'Germany'                => array(
				'iso'  => 'DE',
				'dial' => '49',
			),
			'Ghana'                  => array(
				'iso'  => 'GH',
				'dial' => '233',
			),
			'Greece'                 => array(
				'iso'  => 'GR',
				'dial' => '30',
			),
			'Guatemala'              => array(
				'iso'  => 'GT',
				'dial' => '502',
			),
			'Honduras'               => array(
				'iso'  => 'HN',
				'dial' => '504',
			),
			'Hong Kong'              => array(
				'iso'  => 'HK',
				'dial' => '852',
			),
			'Hungary'                => array(
				'iso'  => 'HU',
				'dial' => '36',
			),
			'Iceland'                => array(
				'iso'  => 'IS',
				'dial' => '354',
			),
			'India'                  => array(
				'iso'  => 'IN',
				'dial' => '91',
			),
			'Indonesia'              => array(
				'iso'  => 'ID',
				'dial' => '62',
			),
			'Iran'                   => array(
				'iso'  => 'IR',
				'dial' => '98',
			),
			'Iraq'                   => array(
				'iso'  => 'IQ',
				'dial' => '964',
			),
			'Ireland'                => array(
				'iso'  => 'IE',
				'dial' => '353',
			),
			'Israel'                 => array(
				'iso'  => 'IL',
				'dial' => '972',
			),
			'Italy'                  => array(
				'iso'  => 'IT',
				'dial' => '39',
			),
			'Jamaica'                => array(
				'iso'  => 'JM',
				'dial' => '1',
			),
			'Japan'                  => array(
				'iso'  => 'JP',
				'dial' => '81',
			),
			'Jordan'                 => array(
				'iso'  => 'JO',
				'dial' => '962',
			),
			'Kazakhstan'             => array(
				'iso'  => 'KZ',
				'dial' => '7',
			),
			'Kenya'                  => array(
				'iso'  => 'KE',
				'dial' => '254',
			),
			'Kuwait'                 => array(
				'iso'  => 'KW',
				'dial' => '965',
			),
			'Latvia'                 => array(
				'iso'  => 'LV',
				'dial' => '371',
			),
			'Lebanon'                => array(
				'iso'  => 'LB',
				'dial' => '961',
			),
			'Libya'                  => array(
				'iso'  => 'LY',
				'dial' => '218',
			),
			'Lithuania'              => array(
				'iso'  => 'LT',
				'dial' => '370',
			),
			'Luxembourg'             => array(
				'iso'  => 'LU',
				'dial' => '352',
			),
			'Malaysia'               => array(
				'iso'  => 'MY',
				'dial' => '60',
			),
			'Maldives'               => array(
				'iso'  => 'MV',
				'dial' => '960',
			),
			'Malta'                  => array(
				'iso'  => 'MT',
				'dial' => '356',
			),
			'Mexico'                 => array(
				'iso'  => 'MX',
				'dial' => '52',
			),
			'Moldova'                => array(
				'iso'  => 'MD',
				'dial' => '373',
			),
			'Monaco'                 => array(
				'iso'  => 'MC',
				'dial' => '377',
			),
			'Mongolia'               => array(
				'iso'  => 'MN',
				'dial' => '976',
			),
			'Morocco'                => array(
				'iso'  => 'MA',
				'dial' => '212',
			),
			'Myanmar'                => array(
				'iso'  => 'MM',
				'dial' => '95',
			),
			'Nepal'                  => array(
				'iso'  => 'NP',
				'dial' => '977',
			),
			'Netherlands'            => array(
				'iso'  => 'NL',
				'dial' => '31',
			),
			'New Zealand'            => array(
				'iso'  => 'NZ',
				'dial' => '64',
			),
			'Nigeria'                => array(
				'iso'  => 'NG',
				'dial' => '234',
			),
			'North Macedonia'        => array(
				'iso'  => 'MK',
				'dial' => '389',
			),
			'Norway'                 => array(
				'iso'  => 'NO',
				'dial' => '47',
			),
			'Oman'                   => array(
				'iso'  => 'OM',
				'dial' => '968',
			),
			'Pakistan'               => array(
				'iso'  => 'PK',
				'dial' => '92',
			),
			'Panama'                 => array(
				'iso'  => 'PA',
				'dial' => '507',
			),
			'Paraguay'               => array(
				'iso'  => 'PY',
				'dial' => '595',
			),
			'Peru'                   => array(
				'iso'  => 'PE',
				'dial' => '51',
			),
			'Philippines'            => array(
				'iso'  => 'PH',
				'dial' => '63',
			),
			'Poland'                 => array(
				'iso'  => 'PL',
				'dial' => '48',
			),
			'Portugal'               => array(
				'iso'  => 'PT',
				'dial' => '351',
			),
			'Qatar'                  => array(
				'iso'  => 'QA',
				'dial' => '974',
			),
			'Romania'                => array(
				'iso'  => 'RO',
				'dial' => '40',
			),
			'Russia'                 => array(
				'iso'  => 'RU',
				'dial' => '7',
			),
			'Saudi Arabia'           => array(
				'iso'  => 'SA',
				'dial' => '966',
			),
			'Serbia'                 => array(
				'iso'  => 'RS',
				'dial' => '381',
			),
			'Singapore'              => array(
				'iso'  => 'SG',
				'dial' => '65',
			),
			'Slovakia'               => array(
				'iso'  => 'SK',
				'dial' => '421',
			),
			'Slovenia'               => array(
				'iso'  => 'SI',
				'dial' => '386',
			),
			'South Africa'           => array(
				'iso'  => 'ZA',
				'dial' => '27',
			),
			'South Korea'            => array(
				'iso'  => 'KR',
				'dial' => '82',
			),
			'Spain'                  => array(
				'iso'  => 'ES',
				'dial' => '34',
			),
			'Sri Lanka'              => array(
				'iso'  => 'LK',
				'dial' => '94',
			),
			'Sweden'                 => array(
				'iso'  => 'SE',
				'dial' => '46',
			),
			'Switzerland'            => array(
				'iso'  => 'CH',
				'dial' => '41',
			),
			'Taiwan'                 => array(
				'iso'  => 'TW',
				'dial' => '886',
			),
			'Tanzania'               => array(
				'iso'  => 'TZ',
				'dial' => '255',
			),
			'Thailand'               => array(
				'iso'  => 'TH',
				'dial' => '66',
			),
			'Tunisia'                => array(
				'iso'  => 'TN',
				'dial' => '216',
			),
			'Turkey'                 => array(
				'iso'  => 'TR',
				'dial' => '90',
			),
			'Uganda'                 => array(
				'iso'  => 'UG',
				'dial' => '256',
			),
			'Ukraine'                => array(
				'iso'  => 'UA',
				'dial' => '380',
			),
			'United Arab Emirates'   => array(
				'iso'  => 'AE',
				'dial' => '971',
			),
			'United Kingdom'         => array(
				'iso'  => 'GB',
				'dial' => '44',
			),
			'United States'          => array(
				'iso'  => 'US',
				'dial' => '1',
			),
			'Uruguay'                => array(
				'iso'  => 'UY',
				'dial' => '598',
			),
			'Uzbekistan'             => array(
				'iso'  => 'UZ',
				'dial' => '998',
			),
			'Venezuela'              => array(
				'iso'  => 'VE',
				'dial' => '58',
			),
			'Vietnam'                => array(
				'iso'  => 'VN',
				'dial' => '84',
			),
			'Yemen'                  => array(
				'iso'  => 'YE',
				'dial' => '967',
			),
			'Zambia'                 => array(
				'iso'  => 'ZM',
				'dial' => '260',
			),
			'Zimbabwe'               => array(
				'iso'  => 'ZW',
				'dial' => '263',
			),
		);
	}

	/**
	 * All supported country display names.
	 *
	 * @return string[]
	 */
	public static function get_names() {
		return array(
			'Afghanistan',
			'Albania',
			'Algeria',
			'Andorra',
			'Angola',
			'Argentina',
			'Armenia',
			'Australia',
			'Austria',
			'Azerbaijan',
			'Bahrain',
			'Bangladesh',
			'Belarus',
			'Belgium',
			'Belize',
			'Benin',
			'Bhutan',
			'Bolivia',
			'Bosnia and Herzegovina',
			'Botswana',
			'Brazil',
			'Brunei',
			'Bulgaria',
			'Cambodia',
			'Cameroon',
			'Canada',
			'Chile',
			'China',
			'Colombia',
			'Costa Rica',
			'Croatia',
			'Cuba',
			'Cyprus',
			'Czech Republic',
			'Denmark',
			'Dominican Republic',
			'Ecuador',
			'Egypt',
			'El Salvador',
			'Estonia',
			'Ethiopia',
			'Finland',
			'France',
			'Georgia',
			'Germany',
			'Ghana',
			'Greece',
			'Guatemala',
			'Honduras',
			'Hong Kong',
			'Hungary',
			'Iceland',
			'India',
			'Indonesia',
			'Iran',
			'Iraq',
			'Ireland',
			'Israel',
			'Italy',
			'Jamaica',
			'Japan',
			'Jordan',
			'Kazakhstan',
			'Kenya',
			'Kuwait',
			'Latvia',
			'Lebanon',
			'Libya',
			'Lithuania',
			'Luxembourg',
			'Malaysia',
			'Maldives',
			'Malta',
			'Mexico',
			'Moldova',
			'Monaco',
			'Mongolia',
			'Morocco',
			'Myanmar',
			'Nepal',
			'Netherlands',
			'New Zealand',
			'Nigeria',
			'North Macedonia',
			'Norway',
			'Oman',
			'Pakistan',
			'Panama',
			'Paraguay',
			'Peru',
			'Philippines',
			'Poland',
			'Portugal',
			'Qatar',
			'Romania',
			'Russia',
			'Saudi Arabia',
			'Serbia',
			'Singapore',
			'Slovakia',
			'Slovenia',
			'South Africa',
			'South Korea',
			'Spain',
			'Sri Lanka',
			'Sweden',
			'Switzerland',
			'Taiwan',
			'Tanzania',
			'Thailand',
			'Tunisia',
			'Turkey',
			'Uganda',
			'Ukraine',
			'United Arab Emirates',
			'United Kingdom',
			'United States',
			'Uruguay',
			'Uzbekistan',
			'Venezuela',
			'Vietnam',
			'Yemen',
			'Zambia',
			'Zimbabwe',
		);
	}
}
