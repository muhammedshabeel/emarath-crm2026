<?php
if (!defined('ABSPATH')) exit;

/* Roles */
add_action('init', function () {
    if (!get_role('sales_agent')) {
        add_role('sales_agent', 'Sales Agent', ['read' => true]);
    }
});

/* Login redirects */
add_filter('login_redirect', function ($redirect_to, $request, $user) {
    if (!is_object($user) || empty($user->roles)) return home_url();
    if (in_array('sales_agent', $user->roles, true)) return home_url('/dashboard');
    if (in_array('administrator', $user->roles, true)) return admin_url();
    return home_url();
}, 10, 3);

/* Agents stay out of wp-admin */
add_action('admin_init', function () {
    if (current_user_can('sales_agent') && !wp_doing_ajax()) {
        wp_redirect(home_url('/dashboard'));
        exit;
    }
});

/* Hide top admin bar for agents */
add_filter('show_admin_bar', function ($show) {
    return current_user_can('sales_agent') ? false : $show;
});

/* Login styling */
add_action('login_enqueue_scripts', function () { ?>
<style>
body.login{background:#0f172a}
.login h1 a{display:none!important}
.login form{border-radius:16px;box-shadow:0 12px 32px rgba(0,0,0,.15)}
.wp-core-ui .button-primary{background:#2563eb;border:none;border-radius:10px}
</style>
<?php });

/* Helpers */
function crm_get_agents() {
    return get_users(['role' => 'sales_agent']);
}
function crm_get_statuses() {
    return ['new','cold','warm','won','lost'];
}
function crm_currency_value($value) {
    return function_exists('wc_price') ? wp_strip_all_tags(wc_price($value)) : number_format((float)$value, 2);
}

/* AJAX: get lead */
add_action('wp_ajax_crm_get_lead', function () {
    $id = isset($_POST['lead_id']) ? intval($_POST['lead_id']) : 0;
    if (!$id) wp_send_json_error();

    $notes_log = get_post_meta($id, 'notes_log', true);
    if (!is_array($notes_log)) $notes_log = [];

    wp_send_json([
        'id' => $id,
        'name' => get_the_title($id),
        'phone' => get_post_meta($id,'phone',true),
        'email' => get_post_meta($id,'email',true),
        'lead_source' => get_post_meta($id,'lead_source',true),
        'country' => get_post_meta($id,'country',true),
        'city' => get_post_meta($id,'city',true),
        'agent' => get_post_meta($id,'agent',true),
        'product_id' => get_post_meta($id,'product_id',true),
        'vendor' => get_post_meta($id,'vendor',true),
        'qty' => get_post_meta($id,'qty',true),
        'unit_price' => get_post_meta($id,'unit_price',true),
        'total' => get_post_meta($id,'total',true),
        'status' => get_post_meta($id,'status',true) ?: 'new',
        'call_status' => get_post_meta($id,'call_status',true),
        'customer_path' => get_post_meta($id,'customer_path',true),
        'attempts' => get_post_meta($id,'attempts',true),
        'cs_remark' => get_post_meta($id,'cs_remark',true),
        'followup' => get_post_meta($id,'followup',true),
        'notes' => get_post_meta($id,'notes',true),
        'notes_log' => $notes_log,
    ]);
});

/* AJAX: update lead */
add_action('wp_ajax_crm_update_lead', function () {
    $id = isset($_POST['lead_id']) ? intval($_POST['lead_id']) : 0;
    if (!$id) wp_send_json_error();

    $customer = sanitize_text_field($_POST['customer'] ?? '');
    if ($customer) {
        wp_update_post([
            'ID' => $id,
            'post_title' => $customer,
        ]);
    }

    update_post_meta($id,'phone',sanitize_text_field($_POST['phone'] ?? ''));
    update_post_meta($id,'email',sanitize_email($_POST['email'] ?? ''));
    update_post_meta($id,'lead_source',sanitize_text_field($_POST['lead_source'] ?? ''));
    update_post_meta($id,'country',sanitize_text_field($_POST['country'] ?? ''));
    update_post_meta($id,'city',sanitize_text_field($_POST['city'] ?? ''));
    update_post_meta($id,'agent',intval($_POST['agent'] ?? 0));
    update_post_meta($id,'product_id',intval($_POST['product_id'] ?? 0));
    update_post_meta($id,'vendor',sanitize_text_field($_POST['vendor'] ?? ''));
    update_post_meta($id,'qty',max(1, intval($_POST['qty'] ?? 1)));
    update_post_meta($id,'unit_price',(float)($_POST['unit_price'] ?? 0));
    update_post_meta($id,'total',(float)($_POST['total'] ?? 0));
    update_post_meta($id,'status',sanitize_text_field($_POST['status'] ?? 'new'));
    update_post_meta($id,'call_status',sanitize_text_field($_POST['call_status'] ?? ''));
    update_post_meta($id,'customer_path',sanitize_text_field($_POST['customer_path'] ?? ''));
    update_post_meta($id,'attempts',sanitize_text_field($_POST['attempts'] ?? ''));
    update_post_meta($id,'cs_remark',sanitize_text_field($_POST['cs_remark'] ?? ''));
    update_post_meta($id,'followup',sanitize_text_field($_POST['followup'] ?? ''));
    update_post_meta($id,'notes',sanitize_textarea_field($_POST['notes'] ?? ''));

    wp_send_json_success();
});

/* AJAX: drag status */
add_action('wp_ajax_crm_update_status', function () {
    $id = intval($_POST['lead_id'] ?? 0);
    $status = sanitize_text_field($_POST['status'] ?? '');
    if (!$id || !$status) wp_send_json_error();
    update_post_meta($id, 'status', $status);
    wp_send_json_success();
});

/* AJAX: add note */
add_action('wp_ajax_crm_add_note', function () {
    $id = intval($_POST['lead_id'] ?? 0);
    $note = sanitize_text_field($_POST['note'] ?? '');
    if (!$id || !$note) wp_send_json_error();

    $notes = get_post_meta($id, 'notes_log', true);
    if (!is_array($notes)) $notes = [];
    $notes[] = [
        'text' => $note,
        'time' => current_time('mysql'),
        'user' => wp_get_current_user()->display_name,
    ];
    update_post_meta($id, 'notes_log', $notes);

    wp_send_json_success($notes);
});

/* AJAX: save followup */
add_action('wp_ajax_crm_save_followup', function () {
    $id = intval($_POST['lead_id'] ?? 0);
    $date = sanitize_text_field($_POST['date'] ?? '');
    if (!$id) wp_send_json_error();
    update_post_meta($id, 'followup', $date);
    wp_send_json_success();
});

/* AJAX: create WC order */
add_action('wp_ajax_crm_create_order', function () {
    if (!function_exists('wc_create_order')) wp_send_json_error(['message' => 'WooCommerce not active']);

    $lead_id = intval($_POST['lead_id'] ?? 0);
    $product_id = intval($_POST['product_id'] ?? 0);
    $qty = max(1, intval($_POST['qty'] ?? 1));

    if (!$lead_id || !$product_id) wp_send_json_error(['message' => 'Missing lead or product']);

    $product = wc_get_product($product_id);
    if (!$product) wp_send_json_error(['message' => 'Invalid product']);

    $order = wc_create_order();
    $order->add_product($product, $qty);
    $order->set_address([
        'first_name' => get_the_title($lead_id),
        'phone' => get_post_meta($lead_id,'phone',true),
        'email' => get_post_meta($lead_id,'email',true),
        'city' => get_post_meta($lead_id,'city',true),
        'country' => get_post_meta($lead_id,'country',true),
    ], 'billing');
    $order->calculate_totals();
    $order->update_status('processing');

    update_post_meta($lead_id,'order_id',$order->get_id());
    update_post_meta($lead_id,'status','won');
    update_post_meta($lead_id,'product_id',$product_id);
    update_post_meta($lead_id,'qty',$qty);
    update_post_meta($lead_id,'unit_price',(float)$product->get_price());
    update_post_meta($lead_id,'total',(float)$order->get_total());

    wp_send_json_success(['order_id' => $order->get_id()]);
});

/* AJAX: admin dashboard stats */
add_action('wp_ajax_get_dashboard_stats', 'crm_get_dashboard_stats');
function crm_get_dashboard_stats() {
    $agent = intval($_GET['agent'] ?? 0);
    $status_filter = sanitize_text_field($_GET['status'] ?? '');
    $from = sanitize_text_field($_GET['from'] ?? '');
    $to = sanitize_text_field($_GET['to'] ?? '');

    $meta_query = [];
    if ($agent) {
        $meta_query[] = ['key' => 'agent', 'value' => $agent, 'compare' => '='];
    }
    if ($status_filter) {
        $meta_query[] = ['key' => 'status', 'value' => $status_filter, 'compare' => '='];
    }

    $date_query = [];
    if ($from) $date_query['after'] = $from;
    if ($to) $date_query['before'] = $to;

    $args = [
        'post_type' => 'lead',
        'posts_per_page' => -1,
        'post_status' => 'publish',
    ];
    if (!empty($meta_query)) $args['meta_query'] = $meta_query;
    if (!empty($date_query)) $args['date_query'] = [$date_query];

    $leads = get_posts($args);
    $total = count($leads);
    $won = 0;
    $revenue = 0;
    $orders = 0;
    $pipeline = ['new'=>0,'cold'=>0,'warm'=>0,'won'=>0,'lost'=>0];
    $agent_map = [];
    $recent = [];
    $monthly = [];

    foreach ($leads as $lead) {
        $lead_id = $lead->ID;
        $status = get_post_meta($lead_id,'status',true) ?: 'new';
        $amount = (float)get_post_meta($lead_id,'total',true);
        $agent_id = (int)get_post_meta($lead_id,'agent',true);
        $order_id = (int)get_post_meta($lead_id,'order_id',true);

        if (!isset($pipeline[$status])) $pipeline[$status] = 0;
        $pipeline[$status]++;

        if ($status === 'won') {
            $won++;
            $revenue += $amount;
        }

        if ($order_id) $orders++;

        $month_key = date('Y-m', strtotime($lead->post_date));
        if (!isset($monthly[$month_key])) $monthly[$month_key] = 0;
        $monthly[$month_key] += $amount;

        if (!isset($agent_map[$agent_id])) {
            $user = $agent_id ? get_user_by('id', $agent_id) : false;
            $agent_map[$agent_id] = [
                'name' => $user ? $user->display_name : 'Unassigned',
                'leads' => 0,
                'won' => 0,
                'revenue' => 0,
            ];
        }
        $agent_map[$agent_id]['leads']++;
        if ($status === 'won') {
            $agent_map[$agent_id]['won']++;
            $agent_map[$agent_id]['revenue'] += $amount;
        }

        $recent[] = [
            'name' => get_the_title($lead_id),
            'phone' => get_post_meta($lead_id,'phone',true),
            'status' => $status,
            'amount' => $amount,
            'agent' => $agent_map[$agent_id]['name'] ?? 'Unassigned',
            'date' => mysql2date('Y-m-d H:i', $lead->post_date),
        ];
    }

    ksort($monthly);
    $months = array_map(function($m){ return date('M Y', strtotime($m . '-01')); }, array_keys($monthly));
    $monthly_values = array_values($monthly);

    uasort($agent_map, function($a, $b){ return $b['revenue'] <=> $a['revenue']; });
    $agent_rows = array_values($agent_map);

    usort($recent, function($a, $b){ return strcmp($b['date'], $a['date']); });
    $recent = array_slice($recent, 0, 10);

    $conversion = $total > 0 ? round(($won / $total) * 100, 1) : 0;

    wp_send_json([
        'total' => $total,
        'won' => $won,
        'orders' => $orders,
        'revenue' => crm_currency_value($revenue),
        'revenue_raw' => $revenue,
        'conversion' => $conversion,
        'months' => $months,
        'monthly' => $monthly_values,
        'pipeline' => $pipeline,
        'agents' => $agent_rows,
        'recent' => $recent,
    ]);
}
add_action('wp_enqueue_scripts', function(){
    wp_enqueue_style('crm-style', get_stylesheet_uri());
});