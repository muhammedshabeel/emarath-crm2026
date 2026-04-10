<?php
/**
 * Template Name: Agent Dashboard
 */
if (!defined('ABSPATH')) exit;
if (!is_user_logged_in()) {
    wp_redirect(wp_login_url(home_url('/dashboard')));
    exit;
}
get_header();

$user_id = get_current_user_id();
$is_admin = current_user_can('administrator');

$args = [
  'post_type' => 'lead',
  'posts_per_page' => -1,
  'post_status' => 'publish',
];
if (!$is_admin) {
  $args['meta_query'] = [[
    'key' => 'agent',
    'value' => $user_id,
    'compare' => '=',
  ]];
}
$leads = get_posts($args);
$statuses = crm_get_statuses();
$agents = crm_get_agents();

$products = function_exists('wc_get_products') ? wc_get_products(['limit' => -1, 'status' => 'publish']) : [];
$grouped = array_fill_keys($statuses, []);
$activities = [];

foreach ($leads as $lead) {
  $status = get_post_meta($lead->ID, 'status', true) ?: 'new';
  if (!isset($grouped[$status])) $grouped[$status] = [];
  $grouped[$status][] = $lead;

  $followup = get_post_meta($lead->ID, 'followup', true);
  if ($followup) {
    $activities[] = [
      'name' => get_the_title($lead->ID),
      'followup' => $followup,
      'status' => $status,
      'overdue' => strtotime($followup) < current_time('timestamp'),
    ];
  }
}
usort($activities, function($a, $b){ return strcmp($a['followup'], $b['followup']); });
?>

<div class="crm-app">
  <aside class="crm-sidebar">
    <div class="crm-brand">CRM</div>
    <nav class="crm-nav">
      <a href="#" class="active" data-tab="leads"><span>Leads</span></a>
      <a href="#" data-tab="activity"><span>Activity</span></a>
      <a href="#" data-tab="documents"><span>Documents</span></a>
    </nav>
  </aside>

  <main class="crm-main">
    <div class="crm-topbar">
      <div>
        <div class="crm-title">Agent Dashboard</div>
        <div class="crm-subtitle">Manage leads, follow-ups and orders.</div>
      </div>
      <div class="crm-actions">
        <div class="crm-view-switch">
          <button class="active" data-view="kanban">Kanban</button>
          <button data-view="grid">Grid</button>
          <button data-view="list">List</button>
        </div>
      </div>
    </div>

    <section id="tab-leads" class="crm-section active">
      <div class="crm-kanban" id="crmKanban">
        <?php foreach ($statuses as $status): ?>
          <div class="crm-col" data-status="<?php echo esc_attr($status); ?>">
            <div class="crm-col-head">
              <span><?php echo esc_html(strtoupper($status)); ?></span>
              <span><?php echo count($grouped[$status] ?? []); ?></span>
            </div>

            <?php foreach (($grouped[$status] ?? []) as $lead): ?>
              <?php
                $phone = get_post_meta($lead->ID, 'phone', true);
                $amount = (float)get_post_meta($lead->ID, 'total', true);
              ?>
              <div class="crm-lead-card" draggable="true" data-id="<?php echo (int)$lead->ID; ?>">
                <div class="crm-lead-title"><?php echo esc_html(get_the_title($lead->ID)); ?></div>
                <div class="crm-lead-meta"><?php echo esc_html($phone); ?></div>
                <div class="crm-lead-amount"><?php echo crm_currency_value($amount); ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="crm-grid-cards" id="crmGrid">
        <?php foreach ($leads as $lead): ?>
          <?php $amount = (float)get_post_meta($lead->ID, 'total', true); ?>
          <div class="crm-lead-card" data-id="<?php echo (int)$lead->ID; ?>">
            <div class="crm-lead-title"><?php echo esc_html(get_the_title($lead->ID)); ?></div>
            <div class="crm-lead-meta"><?php echo esc_html(get_post_meta($lead->ID, 'phone', true)); ?></div>
            <div class="crm-lead-amount"><?php echo crm_currency_value($amount); ?></div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="crm-card crm-list-view" id="crmList">
        <div class="crm-table-wrap">
          <table class="crm-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Phone</th>
                <th>Status</th>
                <th>Amount</th>
                <th>Follow-up</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($leads as $lead): ?>
                <?php
                  $status = get_post_meta($lead->ID, 'status', true) ?: 'new';
                  $amount = (float)get_post_meta($lead->ID, 'total', true);
                ?>
                <tr class="crm-open-lead" data-id="<?php echo (int)$lead->ID; ?>">
                  <td><?php echo esc_html(get_the_title($lead->ID)); ?></td>
                  <td><?php echo esc_html(get_post_meta($lead->ID, 'phone', true)); ?></td>
                  <td><span class="crm-badge badge-<?php echo esc_attr($status); ?>"><?php echo esc_html(strtoupper($status)); ?></span></td>
                  <td><?php echo crm_currency_value($amount); ?></td>
                  <td><?php echo esc_html(get_post_meta($lead->ID, 'followup', true)); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <section id="tab-activity" class="crm-section">
      <div class="crm-activity-list">
        <?php foreach ($activities as $item): ?>
          <div class="crm-card crm-activity-card">
            <strong><?php echo esc_html($item['name']); ?></strong>
            <div class="crm-subtitle"><?php echo esc_html($item['followup']); ?></div>
            <div style="margin-top:6px">
              <span class="crm-badge badge-<?php echo esc_attr($item['status']); ?>"><?php echo esc_html(strtoupper($item['status'])); ?></span>
              <?php if ($item['overdue']): ?>
                <span class="crm-overdue">Overdue</span>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <section id="tab-documents" class="crm-section">
      <div class="crm-doc-list">
        <?php
        $docs = get_posts([
          'post_type' => 'post',
          'posts_per_page' => -1,
          'category_name' => 'crm-docs'
        ]);
        if ($docs):
          foreach ($docs as $doc): ?>
            <div class="crm-card crm-doc-card">
              <strong><?php echo esc_html($doc->post_title); ?></strong>
              <div class="crm-subtitle"><?php echo esc_html(wp_trim_words($doc->post_content, 22)); ?></div>
            </div>
          <?php endforeach;
        else: ?>
          <div class="crm-card crm-doc-card">
            <strong>No documents yet</strong>
            <div class="crm-subtitle">Create blog posts in category <code>crm-docs</code> for sales scripts and product notes.</div>
          </div>
        <?php endif; ?>
      </div>
    </section>
  </main>
</div>

<div class="crm-drawer" id="crmDrawer">
  <div class="crm-drawer-top">
    <h2 id="drawerTitle">Lead</h2>
    <button class="crm-drawer-close" id="drawerClose">Close</button>
  </div>

  <div class="crm-inline-links">
    <a href="#" id="drawerCall" target="_blank">Call</a>
    <a href="#" id="drawerWhatsapp" target="_blank">WhatsApp</a>
  </div>

  <input type="hidden" id="lead_id">

  <div class="crm-drawer-grid">
    <div class="full"><input id="customer" placeholder="Customer Name"></div>
    <div><input id="phone" placeholder="Phone"></div>
    <div><input id="email" placeholder="Email"></div>

    <div>
      <select id="lead_source">
        <option value="">Lead Source</option>
        <option>WHATSAPP</option><option>API</option><option>SOCIAL MEDIA</option><option>REFERRAL</option><option>GOOGLE ADS</option>
      </select>
    </div>
    <div><input id="country" placeholder="Country"></div>
    <div><input id="city" placeholder="City"></div>

    <div>
      <select id="agent">
        <option value="">Assign Agent</option>
        <?php foreach ($agents as $agent): ?>
          <option value="<?php echo (int)$agent->ID; ?>"><?php echo esc_html($agent->display_name); ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div>
      <select id="product_id">
        <option value="">Product</option>
        <?php foreach ($products as $product): ?>
          <option value="<?php echo (int)$product->get_id(); ?>" data-price="<?php echo esc_attr((float)$product->get_price()); ?>">
            <?php echo esc_html($product->get_name()); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div><input id="vendor" placeholder="Vendor"></div>
    <div><input id="qty" type="number" min="1" value="1" placeholder="Qty"></div>
    <div><input id="unit_price" type="number" step="0.01" placeholder="Unit Price"></div>
    <div class="full"><input id="total" type="number" step="0.01" placeholder="Total"></div>

    <div>
      <select id="status">
        <?php foreach ($statuses as $status): ?>
          <option value="<?php echo esc_attr($status); ?>"><?php echo esc_html(strtoupper($status)); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div><input id="call_status" placeholder="Call Status"></div>
    <div><input id="customer_path" placeholder="Customer Path"></div>
    <div><input id="attempts" placeholder="Attempts"></div>
    <div class="full"><input id="cs_remark" placeholder="CS Remark"></div>
    <div class="full"><input id="followup" type="datetime-local"></div>
    <div class="full"><textarea id="notes" placeholder="Notes"></textarea></div>
  </div>

  <div style="display:flex;gap:10px;margin-top:14px">
    <button class="crm-btn" id="saveLeadBtn">Save Lead</button>
    <button class="crm-btn-secondary" id="createOrderBtn">Create Order</button>
  </div>

  <div style="margin-top:18px">
    <input id="newNoteText" placeholder="Add note">
    <button class="crm-btn" id="addNoteBtn" style="margin-top:10px">Add Note</button>
  </div>

  <div class="crm-note-list" id="noteList"></div>
</div>

<script>
const drawer = document.getElementById('crmDrawer');

function openLead(id){
  const body = new URLSearchParams({action:'crm_get_lead', lead_id:id});
  fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: body.toString()
  }).then(r=>r.json()).then(d=>{
    document.getElementById('lead_id').value = d.id || '';
    document.getElementById('drawerTitle').textContent = d.name || 'Lead';
    document.getElementById('customer').value = d.name || '';
    document.getElementById('phone').value = d.phone || '';
    document.getElementById('email').value = d.email || '';
    document.getElementById('lead_source').value = d.lead_source || '';
    document.getElementById('country').value = d.country || '';
    document.getElementById('city').value = d.city || '';
    document.getElementById('agent').value = d.agent || '';
    document.getElementById('product_id').value = d.product_id || '';
    document.getElementById('vendor').value = d.vendor || '';
    document.getElementById('qty').value = d.qty || 1;
    document.getElementById('unit_price').value = d.unit_price || '';
    document.getElementById('total').value = d.total || '';
    document.getElementById('status').value = d.status || 'new';
    document.getElementById('call_status').value = d.call_status || '';
    document.getElementById('customer_path').value = d.customer_path || '';
    document.getElementById('attempts').value = d.attempts || '';
    document.getElementById('cs_remark').value = d.cs_remark || '';
    document.getElementById('followup').value = d.followup || '';
    document.getElementById('notes').value = d.notes || '';
    document.getElementById('drawerCall').href = 'tel:' + (d.phone || '');
    document.getElementById('drawerWhatsapp').href = 'https://wa.me/' + (d.phone || '');

    const noteList = document.getElementById('noteList');
    noteList.innerHTML = '';
    (d.notes_log || []).slice().reverse().forEach(item => {
      noteList.innerHTML += `
        <div class="crm-note-item">
          <div>${item.text}</div>
          <div class="crm-note-time">${item.time}${item.user ? ' · ' + item.user : ''}</div>
        </div>
      `;
    });

    drawer.classList.add('active');
  });
}

document.querySelectorAll('.crm-lead-card, .crm-open-lead').forEach(el => {
  el.addEventListener('click', () => openLead(el.dataset.id));
});

document.getElementById('drawerClose').addEventListener('click', () => drawer.classList.remove('active'));

document.getElementById('saveLeadBtn').addEventListener('click', () => {
  const data = new URLSearchParams({
    action:'crm_update_lead',
    lead_id:document.getElementById('lead_id').value,
    customer:document.getElementById('customer').value,
    phone:document.getElementById('phone').value,
    email:document.getElementById('email').value,
    lead_source:document.getElementById('lead_source').value,
    country:document.getElementById('country').value,
    city:document.getElementById('city').value,
    agent:document.getElementById('agent').value,
    product_id:document.getElementById('product_id').value,
    vendor:document.getElementById('vendor').value,
    qty:document.getElementById('qty').value,
    unit_price:document.getElementById('unit_price').value,
    total:document.getElementById('total').value,
    status:document.getElementById('status').value,
    call_status:document.getElementById('call_status').value,
    customer_path:document.getElementById('customer_path').value,
    attempts:document.getElementById('attempts').value,
    cs_remark:document.getElementById('cs_remark').value,
    followup:document.getElementById('followup').value,
    notes:document.getElementById('notes').value
  });

  fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:data.toString()
  }).then(r=>r.json()).then(()=>location.reload());
});

document.getElementById('addNoteBtn').addEventListener('click', () => {
  const text = document.getElementById('newNoteText').value.trim();
  if (!text) return;
  const data = new URLSearchParams({
    action:'crm_add_note',
    lead_id:document.getElementById('lead_id').value,
    note:text
  });
  fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:data.toString()
  }).then(r=>r.json()).then(()=>openLead(document.getElementById('lead_id').value));
});

document.getElementById('createOrderBtn').addEventListener('click', () => {
  const data = new URLSearchParams({
    action:'crm_create_order',
    lead_id:document.getElementById('lead_id').value,
    product_id:document.getElementById('product_id').value,
    qty:document.getElementById('qty').value
  });
  fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:data.toString()
  }).then(r=>r.json()).then(()=>location.reload());
});

document.querySelectorAll('.crm-nav a').forEach(link => {
  link.addEventListener('click', e => {
    e.preventDefault();
    document.querySelectorAll('.crm-nav a').forEach(a => a.classList.remove('active'));
    link.classList.add('active');
    const tab = link.dataset.tab;
    document.querySelectorAll('.crm-section').forEach(sec => sec.classList.remove('active'));
    document.getElementById('tab-' + tab).classList.add('active');
  });
});

document.querySelectorAll('.crm-view-switch button').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.crm-view-switch button').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    const view = btn.dataset.view;
    document.getElementById('crmKanban').style.display = view === 'kanban' ? 'grid' : 'none';
    document.getElementById('crmGrid').style.display = view === 'grid' ? 'grid' : 'none';
    document.getElementById('crmList').style.display = view === 'list' ? 'block' : 'none';
  });
});

document.querySelectorAll('.crm-lead-card').forEach(card => {
  card.addEventListener('dragstart', () => card.classList.add('dragging'));
  card.addEventListener('dragend', () => card.classList.remove('dragging'));
});

document.querySelectorAll('.crm-col').forEach(col => {
  col.addEventListener('dragover', e => {
    e.preventDefault();
    const dragging = document.querySelector('.dragging');
    if (!dragging) return;
    col.appendChild(dragging);
  });
  col.addEventListener('drop', () => {
    const dragging = document.querySelector('.dragging');
    if (!dragging) return;
    const data = new URLSearchParams({
      action:'crm_update_status',
      lead_id:dragging.dataset.id,
      status:col.dataset.status
    });
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:data.toString()
    });
  });
});

document.getElementById('product_id').addEventListener('change', () => {
  const sel = document.getElementById('product_id');
  const price = parseFloat(sel.options[sel.selectedIndex]?.dataset.price || 0);
  document.getElementById('unit_price').value = price || '';
  const qty = parseInt(document.getElementById('qty').value || 1, 10);
  document.getElementById('total').value = (price * qty).toFixed(2);
});

document.getElementById('qty').addEventListener('input', () => {
  const price = parseFloat(document.getElementById('unit_price').value || 0);
  const qty = parseInt(document.getElementById('qty').value || 1, 10);
  document.getElementById('total').value = (price * qty).toFixed(2);
});
</script>

<?php get_footer(); ?>