<?php
/**
 * Mad Baits catalogue taxonomy and attribute definitions.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

/**
 * Range catalogue keys.
 *
 * @return array<string, array{slug: string, label: string}>
 */
function mad_baits_catalogue_get_ranges() {
	return array(
		'ASBO'               => array('slug' => 'asbo', 'label' => 'ASBO'),
		'Pandemic'           => array('slug' => 'pandemic', 'label' => 'Pandemic'),
		'P-Fish'            => array('slug' => 'p-fish-2', 'label' => 'P-Fish'),
		'Nutz Plus'          => array('slug' => 'nutz-plus', 'label' => 'Nutz Plus'),
		'Nutz Banana'        => array('slug' => 'nutz-banana', 'label' => 'Nutz Banana'),
		'Wicked White'       => array('slug' => 'wicked-white', 'label' => 'Wicked White'),
		'BBB'                => array('slug' => 'bbb', 'label' => 'BBB', 'storefront' => false),
		'Calamino'           => array('slug' => 'calamino', 'label' => 'Calamino'),
		'Compulsive Angler'  => array('slug' => 'compulsive-angler', 'label' => 'Compulsive Angler'),
		'STP'                => array('slug' => 'stp', 'label' => 'STP'),
		'Swan Mussel'        => array('slug' => 'swan-mussel', 'label' => 'Swan Mussel', 'storefront' => false),
	);
}

/**
 * Parent categories and range children.
 *
 * @return array<string, array{slug: string, children: string[], optional_children?: bool}>
 */
function mad_baits_catalogue_get_parents() {
	return array(
		'Boilies'                => array(
			'slug'     => 'boilies',
			'children' => array(),
		),
		'Hookbaits'              => array(
			'slug'     => 'hookbaits',
			'children' => array(),
		),
		'Pellets'                => array(
			'slug'     => 'pellets',
			'children' => array(),
		),
		'Liquids'                => array(
			'slug'     => 'liquids',
			'children' => array(),
		),
		'Groundbait & Bag Mix'   => array(
			'slug'               => 'groundbait-bag-mix',
			'children'           => array(),
			'optional_children'  => false,
		),
		'Paste'                  => array(
			'slug'     => 'paste',
			'children' => array(),
		),
		'Sprays'                 => array(
			'slug'     => 'sprays',
			'children' => array(),
		),
		'Bundles & Deals'        => array(
			'slug'     => 'bundles-deals',
			'children' => array(),
		),
		'Clothing'               => array(
			'slug'     => 'clothing',
			'children' => array(),
		),
		'Accessories'            => array(
			'slug'     => 'accessories',
			'children' => array(),
		),
		'Extras'                 => array(
			'slug'     => 'extras',
			'children' => array(),
		),
		'Team & Testing'         => array(
			'slug'     => 'team-testing',
			'children' => array(),
		),
	);
}

/**
 * Product tags grouped for logging (flat list returned for creation).
 *
 * @return array<string, string[]>
 */
function mad_baits_catalogue_get_tag_groups() {
	return array(
		'Range'        => array('ASBO', 'Pandemic', 'P-Fish', 'Nutz Plus', 'Nutz Banana', 'Wicked White', 'BBB', 'Calamino', 'Compulsive Angler', 'STP', 'Swan Mussel'),
		'Profile'      => array('Fishmeal', 'Krill', 'Marine', 'Meaty', 'Savoury', 'High Protein', 'Sweet', 'Cream', 'White Chocolate', 'Nutty', 'Milky', 'Fruity'),
		'Attraction'   => array('High Leakage', 'Instant Action', 'Clouding', 'Food Source', 'Year Round', 'Cold Water'),
		'Season'       => array('Winter', 'Spring', 'Summer', 'Autumn', 'All Season'),
		'Product Type' => array('Shelf Life', 'Freezer', 'Pop Ups', 'Wafters', 'Skinz', 'Liquid Food', 'Feed Pellet', 'Floating Pellet', 'Mixed Pellet'),
		'Usage'        => array('Match The Hatch', 'Snowman', 'Bottom Bait', 'Zig Fishing', 'Bag Fishing', 'Spod Mix', 'PVA Friendly', 'Margin Fishing'),
		'Target'       => array('Big Carp', 'Natural Attraction', 'Match Style', 'Heavy Feeding', 'Instant Session', 'Long Session', 'Day Ticket', 'French Trips'),
		'Visual'       => array('Bright Hookbaits', 'Washed Out', 'Pastel', 'Fluoro', 'Dark Mix', 'Natural Colour'),
		'Selling'      => array('Best Seller', 'New', 'Team Favourite', 'Limited', 'Exclusive', 'Popular', 'Premium Range', 'Compulsive Special'),
	);
}

/**
 * Flat tag list for creation.
 *
 * @return string[]
 */
function mad_baits_catalogue_get_all_tags() {
	$tags = array();
	foreach (mad_baits_catalogue_get_tag_groups() as $group_tags) {
		foreach ($group_tags as $tag) {
			$tags[] = $tag;
		}
	}

	return array_values(array_unique($tags));
}

/**
 * Global product attributes and terms.
 *
 * @return array<string, array{slug: string, terms: string[]}>
 */
function mad_baits_catalogue_get_attributes() {
	return array(
		'Size'            => array(
			'slug'  => 'size',
			'terms' => array('8mm', '10mm', '11mm', '12mm', '14mm', '15mm', '16mm', '18mm', '20mm', '22mm', 'Midi', 'Maxi', 'Mixed'),
		),
		'Weight / Volume' => array(
			'slug'  => 'weight-volume',
			'terms' => array('250ml', '500ml', '1L', '1kg', '5kg', '10kg', '20kg', '25kg', '30kg', '50kg'),
		),
		'Bait Type'       => array(
			'slug'  => 'bait-type',
			'terms' => array(
				'Feed Pellet',
				'Floating Pellet',
				'Liquid Food',
				'Mixed Pellet',
				'Pop Ups',
				'Wafters',
				'Skinz Wafters',
				'Food Dip',
				'Paste',
				'Groundbait',
				'Bag Mix',
				'Spray',
				'Oil',
			),
		),
		'Bait Format'     => array(
			'slug'  => 'bait-format',
			'terms' => array('Shelf Life', 'Freezer', 'Fresh'),
		),
		'Hookbait Type'   => array(
			'slug'  => 'hookbait-type',
			'terms' => array('Pop Ups', 'Wafters', 'Skinz', 'Dumbells', 'Washed Out', 'Barrel Wafters', 'Pastel Pop Ups'),
		),
		'Range'           => array(
			'slug'  => 'range',
			'terms' => array('ASBO', 'BBB', 'Calamino', 'Compulsive Angler', 'Nutz Banana', 'Nutz Plus', 'P-Fish', 'Pandemic', 'Wicked White', 'STP', 'Swan Mussel'),
		),
	);
}

/**
 * Full catalogue definitions (backward compatible shape).
 *
 * @return array<string, mixed>
 */
function mad_baits_catalogue_setup_get_definitions() {
	return array(
		'ranges'     => mad_baits_catalogue_get_ranges(),
		'parents'    => mad_baits_catalogue_get_parents(),
		'tags'       => mad_baits_catalogue_get_all_tags(),
		'tag_groups' => mad_baits_catalogue_get_tag_groups(),
		'attributes' => mad_baits_catalogue_get_attributes(),
	);
}

/**
 * Range colour map for admin UI.
 *
 * @param string $range_key Range definition key.
 * @return string Hex colour.
 */
function mad_baits_catalogue_get_range_colour($range_key) {
	$colours = array(
		'ASBO'              => '#fff202',
		'Pandemic'          => '#e85d04',
		'P-Fish'           => '#0077b6',
		'Nutz Plus'         => '#6a994e',
		'Nutz Banana'       => '#f9c74f',
		'Wicked White'      => '#f8f9fa',
		'BBB'               => '#212529',
		'Calamino'          => '#9d4edd',
		'Compulsive Angler' => '#d62828',
		'STP'               => '#c9a227',
		'Swan Mussel'       => '#1d4e89',
	);

	return isset($colours[ $range_key ]) ? $colours[ $range_key ] : '#646970';
}
