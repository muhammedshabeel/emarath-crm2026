<?php
/**
 * Template Name: Admin Dashboard
 */
if (!defined('ABSPATH')) exit;
if (!current_user_can('administrator')) {
    wp_redirect(home_url('/dashboard'));
    exit;
}
get_header();

$agents = crm_get_agents();
$statuses = crm_get_statuses();
?>

<div class="crm-app">
  <aside class="crm-sidebar">
    <div class="crm-brand">CRM</div>
    <nav class="crm-nav">
      <a href="#" class="active" data-tab="dashboard"><span>Dashboard</span></a>
      <a href="#" data-tab="leads"><span>Leads</span></a>
      <a href="#" data-tab="team"><span>Team</span></a>
      <a href="#" data-tab="orders"><span>Orders</span></a>
      <a href="#" data-tab="reports"><span>Reports</span></a>
    </nav>
  </aside>

  <main class="crm-main">
    <div class="crm-topbar">
      <div>
        <div class="crm-title">Admin Dashboard</div>
        <div class="crm-subtitle">Live insights across leads, agents, orders and revenue.</div>
      </div>
    </div>

    <div class="crm-card crm-filterbar">
      <select id="filterAgent">
        <option value="">All Agents</option>
        <?php foreach ($agents as $agent): ?>
          <option value="<?php echo (int)$agent->ID; ?>"><?php echo esc_html($agent->display_name); ?></option>
        <?php endforeach; ?>
      </select>

      <select id="filterStatus">
        <option value="">All Status</option>
        <?php foreach ($statuses as $status): ?>
          <option value="<?php echo esc_attr($status); ?>"><?php echo esc_html(strtoupper($status)); ?></option>
        <?php endforeach; ?>
      </select>

      <input type="date" id="filterFrom">
      <input type="date" id="filterTo">
      <button class="crm-btn" id="applyFilters">Apply</button>
      <button class="crm-btn-secondary" id="clearFilters">Clear</button>
    </div>

    <section id="tab-dashboard" class="crm-section active">
      <div class="crm-grid-4">
        <div class="crm-card crm-kpi">
          <div class="label">Total Leads</div>
          <div class="value" id="kpiLeads">0</div>
          <div class="sub">All matching leads</div>
        </div>
        <div class="crm-card crm-kpi">
          <div class="label">Won Deals</div>
          <div class="value" id="kpiWon">0</div>
          <div class="sub">Closed successfully</div>
        </div>
        <div class="crm-card crm-kpi">
          <div class="label">Orders</div>
          <div class="value" id="kpiOrders">0</div>
          <div class="sub">WooCommerce linked</div>
        </div>
        <div class="crm-card crm-kpi">
          <div class="label">Revenue</div>
          <div class="value" id="kpiRevenue">0</div>
          <div class="sub"><span id="kpiConversion">0%</span> conversion</div>
        </div>
      </div>

      <div class="crm-charts">
        <div class="crm-card crm-chart-card">
          <h3>Revenue Trend</h3>
          <canvas id="revenueChart"></canvas>
        </div>
        <div class="crm-card crm-chart-card">
          <h3>Pipeline Funnel</h3>
          <canvas id="pipelineChart"></canvas>
        </div>
      </div>

      <div class="crm-grid-2" style="margin-top:16px">
        <div class="crm-card crm-panel">
          <h3>Agent Performance</h3>
          <div class="crm-table-wrap">
            <table class="crm-table">
              <thead>
                <tr>
                  <th>Agent</th>
                  <th>Leads</th>
                  <th>Won</th>
                  <th>Revenue</th>
                  <th>Conversion %</th>
                </tr>
              </thead>
              <tbody id="agentPerformanceBody"></tbody>
            </table>
          </div>
        </div>

        <div class="crm-card crm-panel">
          <h3>Recent Leads</h3>
          <div class="crm-table-wrap">
            <table class="crm-table">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Status</th>
                  <th>Agent</th>
                  <th>Amount</th>
                </tr>
              </thead>
              <tbody id="recentLeadsBody"></tbody>
            </table>
          </div>
        </div>
      </div>
    </section>

    <section id="tab-leads" class="crm-section">
      <div class="crm-card crm-panel">
        <h3>Lead Overview</h3>
        <p class="crm-subtitle">Use the dashboard filters above to review lead volume, conversion and ownership.</p>
      </div>
    </section>

    <section id="tab-team" class="crm-section">
      <div class="crm-card crm-panel">
        <h3>Team Snapshot</h3>
        <div id="teamSnapshot"></div>
      </div>
    </section>

    <section id="tab-orders" class="crm-section">
      <div class="crm-card crm-panel">
        <h3>Orders Snapshot</h3>
        <p class="crm-subtitle">Orders shown in KPI count are generated from linked WooCommerce orders on won leads.</p>
      </div>
    </section>

    <section id="tab-reports" class="crm-section">
      <div class="crm-card crm-panel">
        <h3>Business Insights</h3>
        <ul id="insightList" style="margin:0;padding-left:18px"></ul>
      </div>
    </section>
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let revenueChart, pipelineChart;

function badgeClass(status){
  return 'crm-badge badge-' + status;
}

function buildInsights(data){
  const insights = [];
  if (data.total === 0) insights.push('No leads match the current filters.');
  if (data.conversion < 10) insights.push('Conversion is low. Review follow-up quality and warm lead handling.');
  if (data.conversion >= 10) insights.push('Conversion is healthy. Double down on channels producing won deals.');
  const topAgent = data.agents && data.agents.length ? data.agents[0] : null;
  if (topAgent) insights.push(`Top revenue agent: ${topAgent.name} with ${topAgent.revenue.toFixed(2)} total.`);
  const stalled = (data.pipeline.cold || 0) + (data.pipeline.warm || 0);
  if (stalled > data.won) insights.push('Large mid-pipeline volume detected. Push follow-ups and product offers.');
  return insights;
}

function renderStats(data){
  document.getElementById('kpiLeads').textContent = data.total;
  document.getElementById('kpiWon').textContent = data.won;
  document.getElementById('kpiOrders').textContent = data.orders;
  document.getElementById('kpiRevenue').textContent = data.revenue;
  document.getElementById('kpiConversion').textContent = data.conversion + '%';

  const agentBody = document.getElementById('agentPerformanceBody');
  agentBody.innerHTML = '';
  (data.agents || []).forEach(agent => {
    const conv = agent.leads > 0 ? ((agent.won / agent.leads) * 100).toFixed(1) : '0.0';
    agentBody.innerHTML += `
      <tr>
        <td>${agent.name}</td>
        <td>${agent.leads}</td>
        <td>${agent.won}</td>
        <td>${agent.revenue.toFixed(2)}</td>
        <td>${conv}%</td>
      </tr>
    `;
  });

  const recentBody = document.getElementById('recentLeadsBody');
  recentBody.innerHTML = '';
  (data.recent || []).forEach(lead => {
    recentBody.innerHTML += `
      <tr>
        <td>${lead.name}<div class="crm-subtitle">${lead.phone || ''}</div></td>
        <td><span class="${badgeClass(lead.status)}">${lead.status.toUpperCase()}</span></td>
        <td>${lead.agent}</td>
        <td>${lead.amount.toFixed(2)}</td>
      </tr>
    `;
  });

  const teamSnapshot = document.getElementById('teamSnapshot');
  teamSnapshot.innerHTML = (data.agents || []).map(agent => `
    <div class="crm-card" style="padding:14px;margin-bottom:10px">
      <strong>${agent.name}</strong>
      <div class="crm-subtitle">${agent.leads} leads · ${agent.won} won · ${agent.revenue.toFixed(2)} revenue</div>
    </div>
  `).join('');

  const insightList = document.getElementById('insightList');
  insightList.innerHTML = buildInsights(data).map(item => `<li style="margin-bottom:10px">${item}</li>`).join('');

  if (revenueChart) revenueChart.destroy();
  revenueChart = new Chart(document.getElementById('revenueChart'), {
    type: 'line',
    data: {
      labels: data.months,
      datasets: [{
        label: 'Revenue',
        data: data.monthly,
        borderColor: '#2563eb',
        backgroundColor: 'rgba(37,99,235,.08)',
        fill: true,
        tension: .35
      }]
    },
    options: { responsive: true, plugins:{legend:{display:true}} }
  });

  if (pipelineChart) pipelineChart.destroy();
  pipelineChart = new Chart(document.getElementById('pipelineChart'), {
    type: 'doughnut',
    data: {
      labels: ['New','Cold','Warm','Won','Lost'],
      datasets: [{
        data: [
          data.pipeline.new || 0,
          data.pipeline.cold || 0,
          data.pipeline.warm || 0,
          data.pipeline.won || 0,
          data.pipeline.lost || 0
        ]
      }]
    },
    options: { responsive: true }
  });
}

function loadAdminStats(){
  const params = new URLSearchParams({
    action: 'get_dashboard_stats',
    agent: document.getElementById('filterAgent').value,
    status: document.getElementById('filterStatus').value,
    from: document.getElementById('filterFrom').value,
    to: document.getElementById('filterTo').value
  });

  fetch('<?php echo admin_url('admin-ajax.php'); ?>?' + params.toString())
    .then(r => r.json())
    .then(renderStats);
}

document.getElementById('applyFilters').addEventListener('click', loadAdminStats);
document.getElementById('clearFilters').addEventListener('click', () => {
  document.getElementById('filterAgent').value = '';
  document.getElementById('filterStatus').value = '';
  document.getElementById('filterFrom').value = '';
  document.getElementById('filterTo').value = '';
  loadAdminStats();
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

loadAdminStats();
</script>

<?php get_footer(); ?>