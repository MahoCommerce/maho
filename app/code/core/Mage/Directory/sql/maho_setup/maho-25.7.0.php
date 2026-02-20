<?php

/**
 * Maho
 *
 * @package    Mage_Directory
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

/**
 * Create table 'directory/country_region_name'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('directory/country_name'))
    ->addColumn('locale', Maho\Db\Ddl\Table::TYPE_TEXT, 8, [
        'nullable'  => false,
        'primary'   => true,
        'default'   => '',
    ], 'Locale')
    ->addColumn('country_id', Maho\Db\Ddl\Table::TYPE_TEXT, 2, [
        'nullable'  => false,
        'primary'   => true,
        'default'   => '',
    ], 'Country Id')
    ->addColumn('name', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
        'nullable'  => true,
        'default'   => null,
    ], 'Country Name')
    ->addIndex(
        $installer->getIdxName('directory/country_name', ['country_id']),
        ['country_id'],
    )
    ->addForeignKey(
        $installer->getFkName('directory/country_name', 'country_id', 'directory/country', 'country_id'),
        'country_id',
        $installer->getTable('directory/country'),
        'country_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->setComment('Directory Country Name');
$installer->getConnection()->createTable($table);

/**
 * Fill table directory/country_name for en_US locale
 */
$data = [
    ['AC', 'Ascension Island'], ['AD', 'Andorra'], ['AE', 'United Arab Emirates'],
    ['AF', 'Afghanistan'], ['AG', 'Antigua and Barbuda'], ['AI', 'Anguilla'],
    ['AL', 'Albania'], ['AM', 'Armenia'], ['AN', 'Netherlands Antilles'],
    ['AO', 'Angola'], ['AQ', 'Antarctica'], ['AR', 'Argentina'],
    ['AS', 'American Samoa'], ['AT', 'Austria'], ['AU', 'Australia'],
    ['AW', 'Aruba'], ['AX', 'Åland Islands'], ['AZ', 'Azerbaijan'],
    ['BA', 'Bosnia and Herzegovina'], ['BB', 'Barbados'], ['BD', 'Bangladesh'],
    ['BE', 'Belgium'], ['BF', 'Burkina Faso'], ['BG', 'Bulgaria'],
    ['BH', 'Bahrain'], ['BI', 'Burundi'], ['BJ', 'Benin'],
    ['BL', 'Saint Barthélemy'], ['BM', 'Bermuda'], ['BN', 'Brunei'],
    ['BO', 'Bolivia'], ['BQ', 'Caribbean Netherlands'], ['BR', 'Brazil'],
    ['BS', 'Bahamas'], ['BT', 'Bhutan'], ['BV', 'Bouvet Island'],
    ['BW', 'Botswana'], ['BY', 'Belarus'], ['BZ', 'Belize'],
    ['CA', 'Canada'], ['CC', 'Cocos (Keeling) Islands'], ['CD', 'Congo - Kinshasa'],
    ['CF', 'Central African Republic'], ['CG', 'Congo - Brazzaville'], ['CH', 'Switzerland'],
    ['CI', 'Côte d’Ivoire'], ['CK', 'Cook Islands'], ['CL', 'Chile'],
    ['CM', 'Cameroon'], ['CN', 'China'], ['CO', 'Colombia'],
    ['CP', 'Clipperton Island'], ['CR', 'Costa Rica'], ['CU', 'Cuba'],
    ['CV', 'Cape Verde'], ['CW', 'Curaçao'], ['CX', 'Christmas Island'],
    ['CY', 'Cyprus'], ['CZ', 'Czech Republic'], ['DE', 'Germany'],
    ['DG', 'Diego Garcia'], ['DJ', 'Djibouti'], ['DK', 'Denmark'],
    ['DM', 'Dominica'], ['DO', 'Dominican Republic'], ['DZ', 'Algeria'],
    ['EA', 'Ceuta and Melilla'], ['EC', 'Ecuador'], ['EE', 'Estonia'],
    ['EG', 'Egypt'], ['EH', 'Western Sahara'], ['ER', 'Eritrea'],
    ['ES', 'Spain'], ['ET', 'Ethiopia'], ['EU', 'European Union'],
    ['FI', 'Finland'], ['FJ', 'Fiji'], ['FK', 'Falkland Islands'],
    ['FM', 'Micronesia'], ['FO', 'Faroe Islands'], ['FR', 'France'],
    ['GA', 'Gabon'], ['GB', 'United Kingdom'], ['GD', 'Grenada'],
    ['GE', 'Georgia'], ['GF', 'French Guiana'], ['GG', 'Guernsey'],
    ['GH', 'Ghana'], ['GI', 'Gibraltar'], ['GL', 'Greenland'],
    ['GM', 'Gambia'], ['GN', 'Guinea'], ['GP', 'Guadeloupe'],
    ['GQ', 'Equatorial Guinea'], ['GR', 'Greece'], ['GS', 'South Georgia & South Sandwich Islands'],
    ['GT', 'Guatemala'], ['GU', 'Guam'], ['GW', 'Guinea-Bissau'],
    ['GY', 'Guyana'], ['HK', 'Hong Kong SAR China'], ['HM', 'Heard & McDonald Islands'],
    ['HN', 'Honduras'], ['HR', 'Croatia'], ['HT', 'Haiti'],
    ['HU', 'Hungary'], ['IC', 'Canary Islands'], ['ID', 'Indonesia'],
    ['IE', 'Ireland'], ['IL', 'Israel'], ['IM', 'Isle of Man'],
    ['IN', 'India'], ['IO', 'British Indian Ocean Territory'], ['IQ', 'Iraq'],
    ['IR', 'Iran'], ['IS', 'Iceland'], ['IT', 'Italy'],
    ['JE', 'Jersey'], ['JM', 'Jamaica'], ['JO', 'Jordan'],
    ['JP', 'Japan'], ['KE', 'Kenya'], ['KG', 'Kyrgyzstan'],
    ['KH', 'Cambodia'], ['KI', 'Kiribati'], ['KM', 'Comoros'],
    ['KN', 'Saint Kitts and Nevis'], ['KP', 'North Korea'], ['KR', 'South Korea'],
    ['KW', 'Kuwait'], ['KY', 'Cayman Islands'], ['KZ', 'Kazakhstan'],
    ['LA', 'Laos'], ['LB', 'Lebanon'], ['LC', 'Saint Lucia'],
    ['LI', 'Liechtenstein'], ['LK', 'Sri Lanka'], ['LR', 'Liberia'],
    ['LS', 'Lesotho'], ['LT', 'Lithuania'], ['LU', 'Luxembourg'],
    ['LV', 'Latvia'], ['LY', 'Libya'], ['MA', 'Morocco'],
    ['MC', 'Monaco'], ['MD', 'Moldova'], ['ME', 'Montenegro'],
    ['MF', 'Saint Martin'], ['MG', 'Madagascar'], ['MH', 'Marshall Islands'],
    ['MK', 'Macedonia'], ['ML', 'Mali'], ['MM', 'Myanmar (Burma)'],
    ['MN', 'Mongolia'], ['MO', 'Macau SAR China'], ['MP', 'Northern Mariana Islands'],
    ['MQ', 'Martinique'], ['MR', 'Mauritania'], ['MS', 'Montserrat'],
    ['MT', 'Malta'], ['MU', 'Mauritius'], ['MV', 'Maldives'],
    ['MW', 'Malawi'], ['MX', 'Mexico'], ['MY', 'Malaysia'],
    ['MZ', 'Mozambique'], ['NA', 'Namibia'], ['NC', 'New Caledonia'],
    ['NE', 'Niger'], ['NF', 'Norfolk Island'], ['NG', 'Nigeria'],
    ['NI', 'Nicaragua'], ['NL', 'Netherlands'], ['NO', 'Norway'],
    ['NP', 'Nepal'], ['NR', 'Nauru'], ['NU', 'Niue'],
    ['NZ', 'New Zealand'], ['OM', 'Oman'], ['PA', 'Panama'],
    ['PE', 'Peru'], ['PF', 'French Polynesia'], ['PG', 'Papua New Guinea'],
    ['PH', 'Philippines'], ['PK', 'Pakistan'], ['PL', 'Poland'],
    ['PM', 'Saint Pierre and Miquelon'], ['PN', 'Pitcairn Islands'], ['PR', 'Puerto Rico'],
    ['PS', 'Palestinian Territories'], ['PT', 'Portugal'], ['PW', 'Palau'],
    ['PY', 'Paraguay'], ['QA', 'Qatar'], ['QO', 'Outlying Oceania'],
    ['RE', 'Réunion'], ['RO', 'Romania'], ['RS', 'Serbia'],
    ['RU', 'Russia'], ['RW', 'Rwanda'], ['SA', 'Saudi Arabia'],
    ['SB', 'Solomon Islands'], ['SC', 'Seychelles'], ['SD', 'Sudan'],
    ['SE', 'Sweden'], ['SG', 'Singapore'], ['SH', 'Saint Helena'],
    ['SI', 'Slovenia'], ['SJ', 'Svalbard and Jan Mayen'], ['SK', 'Slovakia'],
    ['SL', 'Sierra Leone'], ['SM', 'San Marino'], ['SN', 'Senegal'],
    ['SO', 'Somalia'], ['SR', 'Suriname'], ['SS', 'South Sudan'],
    ['ST', 'São Tomé and Príncipe'], ['SV', 'El Salvador'], ['SX', 'Sint Maarten'],
    ['SY', 'Syria'], ['SZ', 'Swaziland'], ['TA', 'Tristan da Cunha'],
    ['TC', 'Turks and Caicos Islands'], ['TD', 'Chad'], ['TF', 'French Southern Territories'],
    ['TG', 'Togo'], ['TH', 'Thailand'], ['TJ', 'Tajikistan'],
    ['TK', 'Tokelau'], ['TL', 'Timor-Leste'], ['TM', 'Turkmenistan'],
    ['TN', 'Tunisia'], ['TO', 'Tonga'], ['TR', 'Turkey'],
    ['TT', 'Trinidad and Tobago'], ['TV', 'Tuvalu'], ['TW', 'Taiwan'],
    ['TZ', 'Tanzania'], ['UA', 'Ukraine'], ['UG', 'Uganda'],
    ['UM', 'U.S. Outlying Islands'], ['US', 'United States'], ['UY', 'Uruguay'],
    ['UZ', 'Uzbekistan'], ['VA', 'Vatican City'], ['VC', 'St. Vincent & Grenadines'],
    ['VE', 'Venezuela'], ['VG', 'British Virgin Islands'], ['VI', 'U.S. Virgin Islands'],
    ['VN', 'Vietnam'], ['VU', 'Vanuatu'], ['WF', 'Wallis and Futuna'],
    ['WS', 'Samoa'], ['XK', 'Kosovo'], ['YE', 'Yemen'],
    ['YT', 'Mayotte'], ['ZA', 'South Africa'], ['ZM', 'Zambia'],
    ['ZW', 'Zimbabwe'], ['ZZ', 'Unknown Region'],
];

foreach ($data as $row) {
    $bind = [
        'locale'     => 'en_US',
        'country_id' => $row[0],
        'name'       => $row[1],
    ];
    $installer->getConnection()->insert($installer->getTable('directory/country_name'), $bind);
}

$installer->endSetup();
