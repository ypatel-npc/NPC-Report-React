<?php

namespace MyWPReact\Admin;

if (!defined('ABSPATH')) {
	exit;
}

class Admin
{
	private $plugin_page_hook;

	public function __construct()
	{
		// Register REST API routes immediately
		add_action('rest_api_init', [$this, 'register_rest_routes']);
	}

	public function init()
	{
		// Admin pages
		add_action('admin_menu', [$this, 'add_menu_page']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
	}

	public function register_rest_routes()
	{
		register_rest_route('npc-report/v1', '/stats', array(
			'methods' => \WP_REST_Server::READABLE,
			'callback' => array($this, 'get_stats_data'),
			'permission_callback' => function ($request) {
				// Verify if user is admin
				if (!current_user_can('manage_options')) {
					return new \WP_Error(
						'rest_forbidden',
						'Sorry, you are not allowed to do that.',
						array('status' => 401)
					);
				}
				return true;
			}
		));

		register_rest_route('npc-report/v1', '/orders', array(
			'methods' => \WP_REST_Server::READABLE,
			'callback' => array($this, 'get_filtered_orders'),
			'permission_callback' => function ($request) {
				return true;
			}
		));
	}


	function get_filtered_orders($request)
	{
		global $wpdb;

		// Sanitize and fetch query parameters
		$status = sanitize_text_field($request->get_param('status'));
		$start_date = sanitize_text_field($request->get_param('start_date'));
		$end_date = sanitize_text_field($request->get_param('end_date'));
		$page = (int)($request->get_param('page') ?: 1);
		$per_page = (int)($request->get_param('per_page') ?: 20);
		$offset = ($page - 1) * $per_page;

		// Get orders and total count
		$orders = $this->execute_orders_query($status, $start_date, $end_date, $per_page, $offset);
		$total_orders = $this->get_orders_count($status, $start_date, $end_date);

		// Log results for debugging
		error_log('Orders: ' . print_r($orders, true));
		error_log('Total Orders: ' . $total_orders);

		// Prepare response
		return [
			'orders' => array_map(function ($order) {
				return [
					'id' => $order->order_id,
					// 'date' => $order->order_date ?? null, // Use if `order_date` exists in the result
					'date' => $order->order_update_date ?? null, // Use if `order_update_date` exists in the result
					'status' => str_replace('wc-', '', $order->order_status),
					'type' => $order->order_type,
					'tax' => $order->tax,
					'total' => $order->total,
					// 'line_items' => $order->line_items ?? [] // Use pre-fetched line items if available
					'customer' => $order->username,
				];
			}, $orders),
			'total' => (int)$total_orders,
			'pages' => ceil($total_orders / $per_page)
		];
	}



	// function sget_filtered_orders($request)
	// {
	// 	global $wpdb;
	// 	$status = sanitize_text_field($request->get_param('status'));
	// 	$start_date = sanitize_text_field($request->get_param('start_date'));
	// 	$end_date = sanitize_text_field($request->get_param('end_date'));
	// 	$page = (int)($request->get_param('page') ?: 1);
	// 	$per_page = (int)($request->get_param('per_page') ?: 20);
	// 	$offset = ($page - 1) * $per_page;

	// 	// Get orders and total count
	// 	$orders = $this->execute_orders_query($status, $start_date, $end_date, $per_page, $offset);
	// 	$total_orders = $this->get_orders_count($status, $start_date, $end_date);
	// 	error_log('orders: ' . print_r($orders, true));
	// 	error_log('total_orders: ' . $total_orders);

	// 	// // Get order items if we have orders
	// 	// Get order items for all orders
	// 	if (!empty($orders)) {
	// 		$order_ids = wp_list_pluck($orders, 'order_id');
	// 		$order_ids_string = implode(',', array_map('intval', $order_ids));

	// 		$items_query = "
	//         SELECT 
	//             oi.order_id,
	//             oi.id as item_id,
	//             oi.order_item_name as name,
	//             oi.quantity
	//         FROM {$wpdb->prefix}wc_order_items oi
	//         WHERE oi.order_id IN ($order_ids_string)
	//         AND oi.type = 'line_item'
	//     ";

	// 		error_log('Items Query: ' . $items_query);
	// 		$order_items = $wpdb->get_results($items_query);

	// 		// Organize items by order
	// 		$items_by_order = array();
	// 		foreach ($order_items as $item) {
	// 			if (!isset($items_by_order[$item->order_id])) {
	// 				$items_by_order[$item->order_id] = array();
	// 			}
	// 			$items_by_order[$item->order_id][] = array(
	// 				'id' => $item->item_id,
	// 				'name' => $item->name,
	// 				'quantity' => $item->quantity
	// 			);
	// 		}

	// 		// Add items to orders
	// 		foreach ($orders as &$order) {
	// 			$order->line_items = isset($items_by_order[$order->order_id])
	// 				? $items_by_order[$order->order_id]
	// 				: array();
	// 		}
	// 	}
	// 	return array(
	// 		'orders' => array_map(function ($order) {
	// 			return array(
	// 				'id' => $order->order_id,
	// 				'date' => $order->order_date,
	// 				'status' => str_replace('wc-', '', $order->order_status),
	// 				'type' => $order->order_type,
	// 				'tax' => $order->tax,
	// 				'total' => $order->total,
	// 				'line_items' => $order->line_items ?? [] // Add line items to response
	// 			);
	// 		}, $orders),
	// 		'total' => (int)$total_orders,
	// 		'pages' => ceil($total_orders / $per_page)
	// 	);
	// }


	public function get_stats_data($request)
	{
		// Get and validate date parameters
		$start_date = $request->get_param('start_date');
		$end_date = $request->get_param('end_date');

		// Validate dates
		if (!$this->validate_dates($start_date, $end_date)) {
			return new \WP_Error(
				'invalid_dates',
				'Invalid date range provided.',
				array('status' => 400)
			);
		}

		// Get stats with date range
		$stats =
			[
				[
					'title' => 'Shipped Orders',
					'value' => $this->get_shipped_orders($start_date, $end_date),
					'icon' => 'dashicons-chart-bar',
					'color' => '#2196F3',
					'status' => 'wc-shipped'
				],
				[
					'title' => 'Warranty shipped',
					'value' => $this->get_warranty_shipped($start_date, $end_date),
					'icon' => 'dashicons-chart-bar',
					'color' => '#2196F3',
					'status' => 'wc-shipped-warranty'
				],
				[
					'title' => 'Refunded Orders',
					'value' => $this->get_refunded_orders($start_date, $end_date),
					'icon' => 'dashicons-chart-bar',
					'color' => '#F44336',
					'status' => 'wc-refunded'
				],
				[
					'title' => 'Completed Orders',
					'value' => $this->get_completed_orders($start_date, $end_date),
					'icon' => 'dashicons-groups',
					'color' => '#4CAF50',
					'status' => 'wc-completed'
				],
				[
					'title' => 'Quote',
					'value' => $this->get_quote_orders($start_date, $end_date),
					'icon' => 'dashicons-groups',
					'color' => '#FF9800',
					'status' => 'wc-quote'
				],
				[
					'title' => 'Completed Warranty',
					'value' => $this->get_completed_warranty($start_date, $end_date),
					'icon' => 'dashicons-cart',
					'color' => '#4CAF50',
					'status' => 'wc-completed-warranty'
				],
				[
					'title' => 'Awaiting Pickup',
					'value' => $this->get_awaiting_pickup($start_date, $end_date),
					'icon' => 'dashicons-cart',
					'color' => '#9C27B0',
					'status' => 'wc-awaiting-pickup'
				],
				[
					'title' => 'Awaiting Warranty Pickup',
					'value' => $this->get_awaiting_warranty_pickup($start_date, $end_date),
					'icon' => 'dashicons-cart',
					'color' => '#9C27B0',
					'status' => 'wc-w-await-pickup'
				],
				// [
				// 	'title' => 'tax total',
				// 	'note' => 'total tax collected',
				// 	'value' => $this->get_tax_total($start_date, $end_date),
				// 	'icon' => 'dashicons-cart',
				// 	'color' => '#9C27B0'
				// ],
				// [
				// 	'title' => 'net profit total',
				// 	'note' => 'after removing tax',
				// 	'value' => $this->get_net_profit_total($start_date, $end_date),
				// 	'icon' => 'dashicons-cart',
				// 	'color' => '#9C27B0'
				// ],
				// [
				// 	'title' => 'total sales',
				// 	'note' => 'total sales with tax and net profit included',
				// 	'value' => $this->get_total_sales($start_date, $end_date),
				// 	'icon' => 'dashicons-cart',
				// 	'color' => '#9C27B0'
				// ],
			];

		return rest_ensure_response($stats);
	}

	private function validate_dates($start_date, $end_date)
	{
		if (empty($start_date) || empty($end_date)) {
			return false;
		}

		$start = strtotime($start_date);
		$end = strtotime($end_date);

		if (!$start || !$end || $start > $end) {
			return false;
		}

		return true;
	}

	private function get_tax_total($start_date, $end_date)
	{
		global $wpdb;

		// 	$query = $wpdb->prepare("
		//     SELECT SUM(om.meta_value) AS total_tax
		//     FROM {$wpdb->prefix}woocommerce_order_items oi
		//     INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta om 
		//         ON oi.order_item_id = om.order_item_id
		//     INNER JOIN {$wpdb->prefix}posts p 
		//         ON oi.order_id = p.ID
		//     WHERE om.meta_key = 'tax_amount'
		//       AND p.post_type = 'shop_order'
		//       AND p.post_status IN ('wc-completed', 'wc-shipped', 'wc-completed-warranty')
		//       AND p.post_date BETWEEN %s AND %s
		// ", $start_date . ' 00:00:00', $end_date . ' 23:59:59');
		// 	error_log('query: ' . $query);
		// 	$result = $wpdb->get_var($query);
		// 	return $result ? $result : 0;
		$params = [
			'start_date' => $start_date . ' 00:00:00',
			'end_date' => $end_date . ' 23:59:59',
			'conditions' => [
				[
					'status' => ['wc-completed', 'wc-completed-warranty', 'wc-awaiting-pickup', 'wc-shipped', 'wc-shipped-warranty', 'wc-completed-warranty', 'wc-w-await-pickup', 'wc-awaiting-warranty-pickup'],
					'total_amount_operator' => '>',
					'total_amount_value' => 0
				]
			]
		];
		// error_log("params : " . print_r($params, true));

		$results = $this->getCommonOrderStats($params);
		// error_log("tax total : " . print_r($results[0]->total_tax, true));
		return $results[0]->total_tax;
		// if (is_array($results) && count($results) > 0) {
		// 	return count($results);
		// } else {
		// 	return null;
		// }



		// return $results;
	}


	private function get_net_profit_total($start_date, $end_date)
	{
		global $wpdb;

		return '100';
	}

	private function get_total_sales($start_date, $end_date)
	{
		global $wpdb;

		return '100';
	}

	private function get_refunded_orders($start_date, $end_date)
	{
		// $params = [
		// 	'start_date' => '2024-06-01 00:00:00',
		// 	'end_date' => $end_date . ' 23:59:59',
		// 	'statuses' => ['wc-refunded'],
		// 	'like_pattern' => 'Order status changed from % to Refunded'
		// ];

		$params = [
			'start_date' => $start_date . ' 00:00:00',
			'end_date' => $end_date . ' 23:59:59',
			'conditions' => [
				[
					'status' => ['wc-refunded'],
					'like_pattern' => '%Order status changed from % to Refunded%'
				]
			]
		];
		// error_log("params : " . print_r($params, true));

		$results = $this->getCommonOrderStats($params);
		



		// return $results;
	}


	private function get_warranty_shipped($start_date, $end_date)
	{
		// $params = [
		// 	'start_date' => '2024-06-01 00:00:00',
		// 	'end_date' => $end_date . ' 23:59:59',
		// 	'statuses' => ['wc-shipped-warranty'],
		// 	'like_pattern' => '%Order status changed from % to Warranty Shipped%'
		// ];
		$params = [
			'start_date' => $start_date . ' 00:00:00',
			'end_date' => $end_date . ' 23:59:59',
			'conditions' => [
				[
					'status' => ['wc-shipped-warranty'],
					'like_pattern' => '%Order status changed from % to Warranty Shipped%'
				]
			]
		];

		$results = $this->getCommonOrderStats($params);
		// if (is_array($results) && count($results) > 0) {
		// 	return count($results);
		// } else {
		// 	return null;
		// }
		return $results;
	}

	private function get_shipped_orders($start_date, $end_date)
	{

		// $params = [
		// 	'start_date' => '2024-06-01 00:00:00',
		// 	'end_date' => $end_date . ' 23:59:59',
		// 	'statuses' => ['wc-shipped'],
		// 	'like_pattern' => 'Order status changed from % to Shipped'
		// ];

		$params = [
			'start_date' => $start_date . ' 00:00:00',
			'end_date' => $end_date . ' 23:59:59',
			'conditions' => [
				[
					'status' => ['wc-shipped'],
					'like_pattern' => '%Order status changed from % to Shipped%'
				]
			]
		];
		$results = $this->getCommonOrderStats($params);
		// if (is_array($results) && count($results) > 0) {
		// 	return count($results);
		// } else {
		// 	return null;
		// }

		return $results;
	}
	// Update your stat methods to use date range
	private function get_completed_warranty($start_date, $end_date)
	{
		// $params = [
		// 	'start_date' => '2024-06-01 00:00:00',
		// 	'end_date' => $end_date . ' 23:59:59',
		// 	'statuses' => ['wc-completed-warranty'],
		// 	'like_pattern' => '%Order status changed from % to Completed Warranty%'
		// ];

		// $results = $this->getOrderStatusSummary($params);
		// return $results;

		// Example usage:
		$params = [
			'start_date' => $start_date . ' 00:00:00',
			'end_date' => $end_date . ' 23:59:59',
			'conditions' => [
				[
					'status' => ['wc-completed'],
					'like_pattern' => '%Order status changed from % to Completed%',
					'total_amount' => 0
				],
				[
					'status' => ['wc-completed-warranty'],
					'like_pattern' => '%Order status changed from % to Completed Warranty%'
				]
			]
		];

		$results = $this->getCommonOrderStats($params);
		// if (is_array($results) && count($results) > 0) {
		// 	return count($results);
		// } else {
		// 	return null;
		// }
		return $results;
	}

	private function get_completed_orders($start_date, $end_date)
	{
		// $params = [
		// 	'start_date' => '2024-06-01 00:00:00',
		// 	'end_date' => $end_date . ' 23:59:59',
		// 	'statuses' => ['wc-completed'],
		// 	'like_pattern' => 'Order status changed from % to Completed'
		// ];
		$params = [
			'start_date' => $start_date . ' 00:00:00',
			'end_date' => $end_date . ' 23:59:59',
			'conditions' => [
				[
					'status' => ['wc-completed'],
					'like_pattern' => '%Order status changed from % to Completed%'
				]
			]
		];

		$results = $this->getCommonOrderStats($params);
		// if (is_array($results) && count($results) > 0) {
		// 	return count($results);
		// } else {
		// 	return null;
		// }
		return $results;
	}

	private function get_quote_orders($start_date, $end_date)
	{
		// $params = [
		// 	'start_date' => '2024-06-01 00:00:00',
		// 	'end_date' => $end_date . ' 23:59:59',
		// 	'statuses' => ['wc-quote'],
		// 	'like_pattern' => '%Order status changed from % to Quote%'
		// ];
		$params = [
			'start_date' => $start_date . ' 00:00:00',
			'end_date' => $end_date . ' 23:59:59',
			'conditions' => [
				[
					'status' => ['wc-quote'],
					'like_pattern' => '%Order status changed from % to Quote%'
				]
			]
		];
		$results = $this->getCommonOrderStats($params);
		// if (is_array($results) && count($results) > 0) {
		// 	return count($results);
		// } else {
		// 	return null;
		// }
			return $results;
	}

	private function get_awaiting_pickup($start_date, $end_date)
	{
		// $params = [
		// 	'start_date' => '2024-06-01 00:00:00',
		// 	'end_date' => $end_date . ' 23:59:59',
		// 	'statuses' => ['wc-awaiting-pickup'],
		// 	'like_pattern' => '%Order status changed from % to Awaiting Pickup%'
		// ];
		$params = [
			'start_date' => $start_date . ' 00:00:00',
			'end_date' => $end_date . ' 23:59:59',
			'conditions' => [
				[
					'status' => ['wc-awaiting-pickup'],
					'like_pattern' => '%Order status changed from % to Awaiting Pickup%'
				]
			]
		];

		$results = $this->getCommonOrderStats($params);
		// if (is_array($results) && count($results) > 0) {
		// 	return count($results);
		// } else {
		// 	return null;
		// }
		return $results;
	}


	private function get_awaiting_warranty_pickup($start_date, $end_date)
	{
		// $params = [
		// 	'start_date' => '2024-06-01 00:00:00',
		// 	'end_date' => $end_date . ' 23:59:59',
		// 	'statuses' => ['wc-w-await-pickup'],
		// 	'like_pattern' => '%Order status changed from % to Awaiting Warranty Pickup%'
		// ];
		$params = [
			'start_date' => $start_date . ' 00:00:00',
			'end_date' => $end_date . ' 23:59:59',
			'conditions' => [
				[
					'status' => ['wc-w-await-pickup'],
					'like_pattern' => '%Order status changed from % to Awaiting Warranty Pickup%'
				]
			]
		];

		$results = $this->getCommonOrderStats($params);
		// if (is_array($results) && count($results) > 0) {
		// 	return count($results);
		// } else {
		// 	return null;
		// }
			return $results;
	}
	public function add_menu_page()
	{
		$this->plugin_page_hook = add_menu_page(
			'NPC Report',
			'NPC Report',
			'manage_options',
			'npc-report',
			[$this, 'render_admin_page'],
			'dashicons-admin-plugins'
		);
	}

	public function render_admin_page()
	{
		// Add a nonce for security
		wp_nonce_field('my_react_plugin_nonce', 'my_react_plugin_nonce');
		echo '<div id="npc-report-admin"></div>';
	}

	public function enqueue_scripts($hook)
	{
		// Only load on our specific admin page
		if ($hook !== $this->plugin_page_hook) {
			return;
		}

		$version = defined('WP_DEBUG') && WP_DEBUG ? time() : '1.0.0';

		wp_enqueue_script(
			'npc-report-admin',
			NPC_PLUGIN_URL . 'assets/js/dist/bundle.js',
			['wp-element'],
			$version,
			true
		);

		// Add REST API data with proper nonce
		wp_localize_script(
			'npc-report-admin',
			'npcReportData',
			[
				'root' => esc_url_raw(rest_url()),
				'nonce' => wp_create_nonce('wp_rest'),
				'isAdmin' => current_user_can('manage_options'),
				'isProduction' => !defined('WP_DEBUG') || !WP_DEBUG
			]
		);

		// Enqueue the production CSS file with a dependency on 'wp-components'
		wp_enqueue_style(
			'npc-report-admin-css',
			NPC_PLUGIN_URL . 'assets/css/dist/main.css', // Adjust this path as needed
			array(), // WordPress dependencies
			$version,
			'all'
		);
	}


	// private function getCommonOrderStats($params)
	// {
	// 	global $wpdb;

	// 	// Base query structure
	// 	$query = "
	//     SELECT
	//         o.status AS order_status,
	//         COUNT(DISTINCT o.id) AS order_count,
	//         MAX(o.date_created_gmt) AS latest_order_created_date,
	//         MAX(o.date_updated_gmt) AS latest_order_updated_date,
	//         MAX(c.comment_date) AS latest_note_date
	//     FROM
	//         {$wpdb->prefix}wc_orders o
	//     LEFT JOIN
	//         {$wpdb->prefix}wc_order_stats os ON o.id = os.order_id
	//     LEFT JOIN
	//         {$wpdb->prefix}wc_orders_meta om ON o.id = om.order_id
	//     LEFT JOIN
	//         {$wpdb->prefix}comments c ON o.id = c.comment_post_ID 
	//         AND c.comment_type = 'order_note'
	//     WHERE (";

	// 	// Build conditions array
	// 	$conditions = [];
	// 	foreach ($params['conditions'] as $condition) {
	// 		$condition_str = "(o.status = '{$condition['status']}'";

	// 		if (isset($condition['like_pattern'])) {
	// 			$condition_str .= " AND c.comment_content LIKE '{$condition['like_pattern']}'";
	// 		}

	// 		if (isset($condition['total_amount'])) {
	// 			$condition_str .= " AND o.total_amount = {$condition['total_amount']}";
	// 		}

	// 		$condition_str .= ")";
	// 		$conditions[] = $condition_str;
	// 	}

	// 	// Add conditions to query
	// 	$query .= implode(" OR ", $conditions);

	// 	// Add date range
	// 	$query .= ") AND c.comment_date BETWEEN '{$params['start_date']}' AND '{$params['end_date']}'";

	// 	// Add group by and order by
	// 	$query .= "
	//     GROUP BY
	//         o.status
	//     ORDER BY
	//         order_count DESC";

	// 	// Log the query for debugging
	// 	error_log("Common Query: " . $query);

	// 	// Execute query
	// 	$results = $wpdb->get_results($query);

	// 	// error_log("Common Query results: " . print_r($results, true));

	// 	return $results = ($results) ? $results[0]->order_count : null;
	// }



// COUNT(DISTINCT o.id) AS order_count, -- Unique orders count
//             IFNULL(SUM(DISTINCT os.tax_total), 2) AS total_tax, -- Avoid duplicate tax totals
//             IFNULL(SUM(DISTINCT os.total_sales), 2) AS total_sales -- Total sales
	private function getCommonOrderStats($params)
	{
		global $wpdb;

		// Base query structure
		$query = "
        SELECT
            
            COUNT(DISTINCT o.id) AS order_count,
            o.id AS order_id,
            o.status AS order_status,
            os.net_total AS order_net_total,
            om.meta_value AS meta_value,
            c.comment_content AS order_note,
            c.comment_date AS latest_note_date,
            nwcl.first_name,
            nwcl.last_name,
            nwcl.email,
            os.tax_total AS total_tax,
            os.total_sales AS total_sales,
            os.net_total AS order_net_total
        FROM
            {$wpdb->prefix}wc_orders o
        LEFT JOIN
            {$wpdb->prefix}wc_order_stats os ON o.id = os.order_id
        LEFT JOIN
            {$wpdb->prefix}wc_orders_meta om ON o.id = om.order_id
        LEFT JOIN
            {$wpdb->prefix}comments c ON o.id = c.comment_post_ID 
            AND c.comment_type = 'order_note'
        LEFT JOIN
            {$wpdb->prefix}wc_customer_lookup nwcl ON o.customer_id = nwcl.user_id
        WHERE (";

		// Dynamic conditions based on statuses, total_amount, and comment patterns
		$conditions = [];
		foreach ($params['conditions'] as $condition) {
			// $status_list = implode("','", $condition['status']);
			$status_list = is_array($condition['status'])
			? implode("','", $condition['status'])
			: $condition['status'];


			$condition_str = "(o.status IN ('{$status_list}')";

			if (isset($condition['total_amount_operator']) && isset($condition['total_amount_value'])) {
				$operator = $condition['total_amount_operator'];
				$value = $condition['total_amount_value'];
				$condition_str .= " AND o.total_amount {$operator} {$value}";
			}

			if (isset($condition['like_pattern'])) {
				$condition_str .= " AND c.comment_content LIKE '{$condition['like_pattern']}'";
			}

			$condition_str .= ")";
			$conditions[] = $condition_str;
		}

		// Add conditions to query
		$query .= implode(" OR ", $conditions);

		// Add date range filter
		$query .= ") AND c.comment_date BETWEEN %s AND %s";

		// Add grouping and ordering
		// $query .= "
        // GROUP BY o.id
        // ORDER BY o.id ASC";

		// Prepare query with date parameters
		$prepared_query = $wpdb->prepare($query, $params['start_date'], $params['end_date']);

		// Log the query for debugging
		// error_log("Common Query: " . $prepared_query);

		// Execute the query
		$results = $wpdb->get_results($prepared_query);
		error_log("Common Query results: " . print_r($results, true));
		return $results = ($results) ? $results[0]->order_count : null;

		// Return the results	
		// return $results ? $results : null;
	}








	// Function for building and executing the main query
	// Function for building and executing the dynamic query
	// Function for building and executing the dynamic query
	// public function execute_orders_query($status, $start_date, $end_date, $per_page, $offset)
	// {
	// 	global $wpdb;

	// 	// Define status configurations
	// 	$status_configs = $this->get_status_configs();


	// 	// Check if status exists in the configurations
	// 	if (!isset($status_configs[$status])) {
	// 		error_log("Invalid status provided: " . $status);
	// 		return [];
	// 	}

	// 	// Fetch the correct status and like pattern
	// 	$status_value = $status_configs[$status]['status'];
	// 	$like_pattern = $status_configs[$status]['like_pattern'];
	// 	if (isset($status_configs[$status]['total_amount'])) {
	// 		$total_amount = $status_configs[$status]['total_amount'];
	// 	}
	// 	// Base query with dynamic placeholders
	// 	$query = "
	//     SELECT DISTINCT
	//         o.id AS order_id,
	//         o.status AS order_status,
	//         o.`type` AS order_type,
	//         o.tax_amount AS tax,
	//         o.total_amount AS total,
	// 		c.comment_date as order_update_date,
	// 		nwcl.first_name as fname,
	// 		nwcl.last_name as lname,
	// 		nwcl.username as username
	//     FROM {$wpdb->prefix}wc_orders o
	//     LEFT JOIN {$wpdb->prefix}wc_order_stats os ON o.id = os.order_id
	//     LEFT JOIN {$wpdb->prefix}wc_orders_meta om ON o.id = om.order_id
	//     LEFT JOIN {$wpdb->prefix}comments c ON o.id = c.comment_post_ID 
	//         AND c.comment_type = 'order_note'
	// 	left JOIN npcsite_wc_customer_lookup nwcl on o.customer_id = nwcl.user_id 
	//     WHERE (
	//         o.status = %s
	//         AND c.comment_content LIKE %s
	//         AND o.total_amount = %s
	//     )
	//     AND c.comment_date BETWEEN %s AND %s
	//     GROUP BY o.id
	//     ORDER BY o.id ASC
	//     LIMIT %d OFFSET %d
	// ";

	// 	// Safely prepare the query with dynamic parameters
	// 	$prepared_query = $wpdb->prepare(
	// 		$query,
	// 		$status_value,                               // Status (e.g., wc-completed)
	// 		$like_pattern,  // Comment content (LIKE search)
	// 		$total_amount,
	// 		$start_date . ' 00:00:00',                   // Start date (full timestamp)
	// 		$end_date . ' 23:59:59',                     // End date (full timestamp)
	// 		$per_page,                                   // Pagination limit
	// 		$offset                                      // Pagination offset
	// 	);

	// 	// Log the prepared query for debugging purposes
	// 	error_log('Dynamic Orders Query: ' . $prepared_query);
	// 	// error_log('prepared_query: ' . print_r($wpdb->get_results($prepared_query), true));
	// 	// Execute the query and return results

	// 	// return order obj array
	// 	//  [0] => stdClass Object
	// 	//     (
	// 	//         [order_id] => 34711
	// 	//         [order_status] => wc-completed
	// 	//         [order_type] => shop_order
	// 	//         [tax] => 0.00000000
	// 	//         [total] => 372.14000000
	// 	//     )
	// 	return $wpdb->get_results($prepared_query);
	// }



	// Function for getting total count
	// Function for getting total count of orders based on dynamic status and filters
	// public function get_orders_count($status, $start_date, $end_date)
	// {
	// 	global $wpdb;

	// 	$status_configs = $this->get_status_configs();


	// 	// Check if status exists in the configurations
	// 	if (!isset($status_configs[$status])) {
	// 		error_log("Invalid status provided for count: " . $status);
	// 		return 0;
	// 	}

	// 	// Fetch the correct status and like pattern
	// 	$status_value = $status_configs[$status]['status'];
	// 	$like_pattern = $status_configs[$status]['like_pattern'];
	// 	$total_amount = $status_configs[$status]['total_amount'];
	// 	// Base query for counting unique orders
	// 	$query = "
	//     SELECT COUNT(DISTINCT o.id) AS order_count
	//     FROM {$wpdb->prefix}wc_orders o
	//     LEFT JOIN {$wpdb->prefix}comments c ON o.id = c.comment_post_ID 
	//         AND c.comment_type = 'order_note'
	//     WHERE (
	//         o.status = %s
	//         AND c.comment_content LIKE %s
	//         AND o.total_amount %s
	//     )
	//     AND c.comment_date BETWEEN %s AND %s
	// ";

	// 	// Safely prepare the query with dynamic parameters
	// 	$prepared_query = $wpdb->prepare(
	// 		$query,
	// 		$status_value,                                // Status (e.g., wc-completed)
	// 		$like_pattern,   // Comment content (LIKE search)
	// 		$total_amount,
	// 		$start_date . ' 00:00:00',                    // Start date (full timestamp)
	// 		$end_date . ' 23:59:59'                       // End date (full timestamp)
	// 	);

	// 	// Log the prepared query for debugging purposes
	// 	error_log('Dynamic Count Query: ' . $prepared_query);
	// 	// error_log('prepared_query: ' . print_r($wpdb->get_var($prepared_query), true));
	// 	// Execute the query and return the total count
	// 	return (int) $wpdb->get_var($prepared_query);
	// }

	public function get_orders_count($status, $start_date, $end_date)
	{
		global $wpdb;

		// Fetch status configurations
		$status_configs = $this->get_status_configs();

		// Validate the provided status
		if (!isset($status_configs[$status])) {
			error_log("Invalid status provided for count: " . $status);
			return 0;
		}

		// Fetch configuration values
		$status_values = isset($status_configs[$status]['statuses'])
			? $status_configs[$status]['statuses']
			: [$status_configs[$status]['status']]; // Default to single status

		$like_pattern = isset($status_configs[$status]['like_pattern'])
			? $status_configs[$status]['like_pattern']
			: null;

		$total_amount = $status_configs[$status]['total_amount'] ?? null;

		// Base query
		$query = "
        SELECT COUNT(DISTINCT o.id) AS order_count
        FROM {$wpdb->prefix}wc_orders o
        LEFT JOIN {$wpdb->prefix}comments c ON o.id = c.comment_post_ID 
            AND c.comment_type = 'order_note'
        WHERE 1 = 1
    ";

		// Parameters array
		$params = [];

		// Handle multiple statuses
		if (!empty($status_values)) {
			$placeholders = implode(',', array_fill(0, count($status_values), '%s'));
			$query .= " AND o.status IN ($placeholders)";
			$params = array_merge($params, $status_values);
		}

		// Add LIKE condition
		if ($like_pattern) {
			$query .= " AND c.comment_content LIKE %s";
			$params[] = $like_pattern;
		}

		// Add total_amount condition
		if ($total_amount !== null) {
			$total_amount_operator = $status_configs[$status]['total_amount_operator'] ?? '=';
			$query .= " AND o.total_amount {$total_amount_operator} %f";
			$params[] = $total_amount;
		}


		// Add date range condition
		if ($start_date && $end_date) {
			$query .= " AND c.comment_date BETWEEN %s AND %s";
			$params[] = $start_date . ' 00:00:00';
			$params[] = $end_date . ' 23:59:59';
		}

		// Safely prepare the query
		$prepared_query = $wpdb->prepare($query, $params);

		// Debugging: Log the query if WP_DEBUG is enabled
	
			error_log('Prepared Orders Count Query: ' . $prepared_query);
	

		// Execute the query and return the total count
		return (int) $wpdb->get_var($prepared_query);
	}


	public function execute_orders_query($status, $start_date, $end_date, $per_page, $offset)
	{
		global $wpdb;

		// Define status configurations
		$status_configs = $this->get_status_configs();

		// Validate the provided status
		if (!isset($status_configs[$status])) {
			error_log("Invalid status provided: " . $status);
			return [];
		}

		// Fetch configuration values
		$status_values = isset($status_configs[$status]['statuses'])
			? $status_configs[$status]['statuses']
			: [$status_configs[$status]['status']]; // Default to single status

		$like_pattern = isset($status_configs[$status]['like_pattern'])
			? $status_configs[$status]['like_pattern']
			: null;
		$total_amount = $status_configs[$status]['total_amount'] ?? null;

		// Base query
		$query = "
        SELECT DISTINCT
            o.id AS order_id,
            o.status AS order_status,
            o.`type` AS order_type,
            o.tax_amount AS tax,
            o.total_amount AS total,
            c.comment_date AS order_update_date,
            nwcl.first_name AS fname,
            nwcl.last_name AS lname,
            nwcl.username AS username
        FROM {$wpdb->prefix}wc_orders o
        LEFT JOIN {$wpdb->prefix}wc_order_stats os ON o.id = os.order_id
        LEFT JOIN {$wpdb->prefix}wc_orders_meta om ON o.id = om.order_id
        LEFT JOIN {$wpdb->prefix}comments c ON o.id = c.comment_post_ID 
            AND c.comment_type = 'order_note'
        LEFT JOIN {$wpdb->prefix}wc_customer_lookup nwcl ON o.customer_id = nwcl.user_id
        WHERE 1 = 1
    ";

		// Dynamic WHERE conditions
		$params = [];

		// Handle multiple statuses using IN clause
		if (!empty($status_values)) {
			$placeholders = implode(',', array_fill(0, count($status_values), '%s'));
			$query .= " AND o.status IN ($placeholders)";
			$params = array_merge($params, $status_values);
		}

		if ($like_pattern) {
			$query .= " AND c.comment_content LIKE %s";
			$params[] = $like_pattern;
		}

		if ($total_amount !== null) {
			$total_amount_operator = $status_configs[$status]['total_amount_operator'] ?? '=';
			$query .= " AND o.total_amount {$total_amount_operator} %f";
			$params[] = $total_amount;
		}


		if ($start_date && $end_date) {
			$query .= " AND c.comment_date BETWEEN %s AND %s";
			$params[] = $start_date . ' 00:00:00';
			$params[] = $end_date . ' 23:59:59';
		}

		// Append GROUP BY, ORDER BY, LIMIT, and OFFSET
		$query .= "
        GROUP BY o.id
        ORDER BY o.id ASC
        LIMIT %d OFFSET %d
    ";

		$params[] = $per_page;
		$params[] = $offset;

		// Prepare the query safely
		$prepared_query = $wpdb->prepare($query, $params);

		// Debug: Log the query in development mode

		// error_log('Prepared Orders Query: ' . $prepared_query);


		// Execute and return results
		return $wpdb->get_results($prepared_query);
	}



	// Centralized function for status configurations
	public function get_status_configs()
	{
		// return [
		// 	'wc-completed' => [
		// 		'status' => 'wc-completed',
		// 		'like_pattern' => '%Order status changed from % to Completed%',
		// 		'total_amount' => '> 0'
		// 	],
		// 	'wc-awaiting-pickup' => [
		// 		'status' => 'wc-awaiting-pickup',
		// 		'like_pattern' => '%Order status changed from % to Awaiting Pickup%',
		// 		'total_amount' => '> 0'
		// 	],
		// 	'wc-shipped' => [
		// 		'status' => 'wc-shipped',
		// 		'like_pattern' => '%Order status changed from % to Shipped%',
		// 		'total_amount' => '>'.'0'	
		// 	],
		// 	'wc-shipped-warranty' => [
		// 		'status' => 'wc-shipped-warranty',
		// 		'like_pattern' => '%Order status changed from % to Warranty Shipped%',
		// 		'total_amount' => '= 0'
		// 	],
		// 	'wc-completed-warranty' => [
		// 		'statuses' => ['wc-completed', 'wc-completed-warranty'],
		// 		'like_pattern' => '%Order status changed from % to % Warranty%',
		// 		'total_amount' => '= 0'
		// 	],
		// 	'wc-quote' => [
		// 		'status' => 'wc-quote',
		// 		'like_pattern' => '%Order status changed from % to Quote%'
		// 	],
		// 	'wc-refunded' => [
		// 		'status' => 'wc-refunded',
		// 		'like_pattern' => '%Order status changed from % to Refunded%'
		// 	],
		// 	'wc-w-await-pickup' => [
		// 		'status' => 'wc-w-await-pickup',
		// 		'like_pattern' => '%Order status changed from % to Awaiting Warranty Pickup%',
		// 		'total_amount' => '= 0'
		// 	],
		// 	'wc-awaiting-warranty-pickup' => [
		// 		'status' => 'wc-awaiting-warranty-pickup',
		// 		'like_pattern' => '%Order status changed from % to Awaiting Warranty Pickup%',
		// 		'total_amount' => '= 0'
		// 	]
		// 	// Add more statuses here as needed
		// ];

		return [
			'wc-completed' => [
				'status' => 'wc-completed',
				'like_pattern' => '%Order status changed from % to Completed%',
				'total_amount_operator' => '>',
				'total_amount_value' => 0
			],
			'wc-awaiting-pickup' => [
				'status' => 'wc-awaiting-pickup',
				'like_pattern' => '%Order status changed from % to Awaiting Pickup%',
				'total_amount_operator' => '>',
				'total_amount_value' => 0
			],
			'wc-shipped' => [
				'status' => 'wc-shipped',
				'like_pattern' => '%Order status changed from % to Shipped%',
				'total_amount_operator' => '>',
				'total_amount_value' => 0
			],
			'wc-shipped-warranty' => [
				'status' => 'wc-shipped-warranty',
				'like_pattern' => '%Order status changed from % to Warranty Shipped%',
				'total_amount_operator' => '=',
				'total_amount_value' => 0
			],
			'wc-completed-warranty' => [
				'statuses' => ['wc-completed', 'wc-completed-warranty'],
				'like_pattern' => '%Order status changed from % to % Warranty%',
				'total_amount_operator' => '=',
				'total_amount_value' => 0
			],
			'wc-quote' => [
				'status' => 'wc-quote',
				'like_pattern' => '%Order status changed from % to Quote%'
			],
			'wc-refunded' => [
				'status' => 'wc-refunded',
				'like_pattern' => '%Order status changed from % to Refunded%'
			],
			'wc-w-await-pickup' => [
				'status' => 'wc-w-await-pickup',
				'like_pattern' => '%Order status changed from % to Awaiting Warranty Pickup%',
				'total_amount_operator' => '=',
				'total_amount_value' => 0
			],
			'wc-awaiting-warranty-pickup' => [
				'status' => 'wc-awaiting-warranty-pickup',
				'like_pattern' => '%Order status changed from % to Awaiting Warranty Pickup%',
				'total_amount_operator' => '=',
				'total_amount_value' => 0
			]
		];
	}
}

