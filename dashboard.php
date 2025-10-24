<?php
// dashboard.php
// Simple Sales Dashboard (Chart.js + Bootstrap) using mysqli (no PDO)

// ตั้งค่าการเชื่อมต่อฐานข้อมูล
$DB_HOST = 'localhost';
$DB_USER = 's67160379';
$DB_PASS = '3FHs1KGK';
$DB_NAME = 's67160379';

// NOTE: In a real-world scenario, you should use environment variables or a dedicated config file
// for database credentials instead of hardcoding them.

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) {
  http_response_code(500);
  // Log the error instead of showing it directly in a production environment
  die('Database connection failed: ' . $mysqli->connect_error);
}
$mysqli->set_charset('utf8mb4');

function fetch_all($mysqli, $sql) {
  $res = $mysqli->query($sql);
  if (!$res) { 
    // Log the SQL error if the query fails
    error_log("SQL Error: " . $mysqli->error . " in query: " . $sql);
    return []; 
  }
  $rows = [];
  while ($row = $res->fetch_assoc()) { $rows[] = $row; }
  $res->free();
  return $rows;
}

// เตรียมข้อมูลสำหรับกราฟต่าง ๆ
$monthly = fetch_all($mysqli, "SELECT ym, net_sales FROM v_monthly_sales");
$category = fetch_all($mysqli, "SELECT category, net_sales FROM v_sales_by_category");
$region = fetch_all($mysqli, "SELECT region, net_sales FROM v_sales_by_region");
// Ensure only top 10 products are fetched for a clean chart
$topProducts = fetch_all($mysqli, "SELECT product_name, qty_sold, net_sales FROM v_top_products LIMIT 10"); 
$payment = fetch_all($mysqli, "SELECT payment_method, net_sales FROM v_payment_share");
$hourly = fetch_all($mysqli, "SELECT hour_of_day, net_sales FROM v_hourly_sales");
$newReturning = fetch_all($mysqli, "SELECT date_key, new_customer_sales, returning_sales FROM v_new_vs_returning ORDER BY date_key");
$kpis = fetch_all($mysqli, "
  SELECT
    (SELECT SUM(net_amount) FROM fact_sales WHERE date_key >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)) AS sales_30d,
    (SELECT SUM(quantity)   FROM fact_sales WHERE date_key >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)) AS qty_30d,
    (SELECT COUNT(DISTINCT customer_id) FROM fact_sales WHERE date_key >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)) AS buyers_30d
");
$kpi = $kpis ? $kpis[0] : ['sales_30d'=>0,'qty_30d'=>0,'buyers_30d'=>0];

// Helper for number format
function nf($n) { return number_format((float)$n, 2); }

?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Retail DW Dashboard — Sci-Fi Mode</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&family=Share+Tech+Mono&display=swap" rel="stylesheet">
  
  <style>
    /* Sci-Fi Theme: Fonts and Base Colors */
    body { 
        background: #00000a; /* Ultra-dark space blue/black */
        color: #00ffff; /* Primary data color: Cyan/Electric Blue */
        font-family: 'Share Tech Mono', monospace; /* ใช้ฟอนต์ที่อ่านง่ายสำหรับ Body และข้อความส่วนใหญ่ */
        padding-top: 6.5rem; 
    }
    
    /* Navigation Bar - Fixed to top */
    .navbar {
        background-color: rgba(0, 10, 20, 0.95) !important; 
        border-bottom: 2px solid #005577; 
        box-shadow: 0 4px 10px rgba(0, 255, 255, 0.2); 
        padding: 0; 
        z-index: 1030; 
        height: auto; 
        flex-direction: column; 
        align-items: flex-start;
        justify-content: center; 
    }
    .navbar .container-fluid-nav { /* Top row (Brand/Links) */
        display: flex;
        justify-content: space-between;
        width: 100%;
        padding: 0.5rem 1.5rem 0 1.5rem; 
    }

    /* Main Title Styling (Bottom row of navbar) */
    .navbar-title {
        color: #90ee90; 
        text-transform: uppercase;
        letter-spacing: 2px;
        font-size: 1.1rem; 
        font-weight: 700;
        text-align: left;
        padding: 0 1.5rem 0.5rem 1.5rem; 
        width: 100%;
    }

    /* Navbar Elements */
    .navbar-brand {
        color: #90ee90 !important; 
        font-family: 'Orbitron', sans-serif; /* คง Orbitron ไว้สำหรับชื่อแบรนด์เล็ก ๆ */
        font-weight: 700;
        text-shadow: 0 0 5px rgba(144, 238, 144, 0.5);
    }
    
    /* Headings (KPI titles & Chart titles) */
    .card h5 { 
        color: #90ee90; 
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 1.25rem; 
        font-family: 'Share Tech Mono', monospace; /* <<< FIX: เปลี่ยนหัวข้อทั้งหมดเป็น Share Tech Mono เพื่อความอ่านง่าย */
    }

    .text-muted.small {
        color: #00ffff !important; 
        font-size: .85rem !important;
    }
    .btn-outline-secondary {
        color: #ff00ff; 
        border-color: #ff00ff;
        background-color: transparent;
        transition: all 0.2s ease;
    }
    .btn-outline-secondary:hover {
        background-color: #ff00ff;
        color: #00000a;
        border-color: #ff00ff;
    }
    
    /* Global Content Container */
    .container-fluid {
        border: none; 
        padding-bottom: 1rem;
    }
    
    /* Card/Module Styling: The HUD elements */
    .card { 
        background: rgba(1, 15, 30, 0.8); 
        border: 1px solid #005577; 
        border-radius: 0.5rem; 
        box-shadow: 0 0 8px rgba(0, 255, 255, 0.4); 
        transition: all 0.3s ease;
        padding: 1.5rem !important; 
    }
    .card:hover {
        box-shadow: 0 0 15px rgba(0, 255, 255, 0.7); 
    }
    
    /* Key Performance Indicators (KPI values) */
    .kpi { 
        font-size: 2.5rem; 
        font-weight: 700; 
        color: #ff00ff; 
        font-family: 'Share Tech Mono', monospace; 
        text-shadow: 0 0 5px rgba(255, 0, 255, 0.6); 
        line-height: 1; 
    }
    .sub { 
        color: #00ffff; 
        font-size: .8rem; 
        font-family: 'Share Tech Mono', monospace;
    }
    
    /* Grid adjustments */
    .grid { display: grid; gap: 2rem; grid-template-columns: repeat(12, 1fr); } 
    .col-12 { grid-column: span 12; }
    .col-6 { grid-column: span 6; }
    .col-4 { grid-column: span 4; }
    .col-8 { grid-column: span 8; }
    @media (max-width: 991px) {
        .col-6, .col-4, .col-8 { grid-column: span 12; }
    }
    canvas { max-height: 360px; }
  </style>
</head>
<body class="p-3 p-md-4">
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container-fluid-nav">
            <span class="navbar-brand">DATA CORE</span>
            <div class="d-flex align-items-center gap-3">
                <span class="text-muted small">Access: <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Guest_User'); ?></span>
                <a class="btn btn-outline-secondary btn-sm" href="logout.php">LOGOUT</a>
            </div>
        </div>
        <div class="navbar-title">
            RETAIL DW — DASHBOARD INTERFACE
        </div>
    </nav>
    
    <div class="container-fluid">
        <div class="d-flex align-items-center justify-content-end mb-4">
            <span class="sub">DATA SOURCE: MYSQL (MYSQLI)</span>
        </div>

        <div class="grid mb-4">
            <div class="card p-3 col-4">
                <h5>TOTAL NET SALES (30D)</h5>
                <div class="kpi">฿<?= nf($kpi['sales_30d']) ?></div>
            </div>
            <div class="card p-3 col-4">
                <h5>UNITS SOLD (30D)</h5>
                <div class="kpi"><?= number_format((int)$kpi['qty_30d']) ?> UNITS</div>
            </div>
            <div class="card p-3 col-4">
                <h5>UNIQUE BUYERS (30D)</h5>
                <div class="kpi"><?= number_format((int)$kpi['buyers_30d']) ?> USERS</div>
            </div>
        </div>

        <div class="grid">

            <div class="card p-3 col-8">
                <h5>MONTHLY SALES TREND (2 YEARS)</h5>
                <canvas id="chartMonthly"></canvas>
            </div>

            <div class="card p-3 col-4">
                <h5>CATEGORY SALES DISTRIBUTION</h5>
                <canvas id="chartCategory"></canvas>
            </div>

            <div class="card p-3 col-6">
                <h5>TOP 10 PRODUCTS BY QTY SOLD</h5>
                <canvas id="chartTopProducts"></canvas>
            </div>

            <div class="card p-3 col-6">
                <h5>SALES BY GEOGRAPHIC REGION</h5>
                <canvas id="chartRegion"></canvas>
            </div>

            <div class="card p-3 col-6">
                <h5>PAYMENT METHOD BREAKDOWN</h5>
                <canvas id="chartPayment"></canvas>
            </div>

            <div class="card p-3 col-6">
                <h5>HOURLY TRANSACTION VOLUME</h5>
                <canvas id="chartHourly"></canvas>
            </div>

            <div class="card p-3 col-12">
                <h5>NEW VS. RETURNING CUSTOMERS (DAILY)</h5>
                <canvas id="chartNewReturning"></canvas>
            </div>

        </div>
    </div>

<script>
// Prepare data from PHP -> JS
const monthly = <?= json_encode($monthly, JSON_UNESCAPED_UNICODE) ?>;
const category = <?= json_encode($category, JSON_UNESCAPED_UNICODE) ?>;
const region = <?= json_encode($region, JSON_UNESCAPED_UNICODE) ?>;
const topProducts = <?= json_encode($topProducts, JSON_UNESCAPED_UNICODE) ?>;
const payment = <?= json_encode($payment, JSON_UNESCAPED_UNICODE) ?>;
const hourly = <?= json_encode($hourly, JSON_UNESCAPED_UNICODE) ?>;
const newReturning = <?= json_encode($newReturning, JSON_UNESCAPED_UNICODE) ?>;

// Utility: pick labels & values
const toXY = (arr, x, y) => ({ labels: arr.map(o => o[x]), values: arr.map(o => parseFloat(o[y])) });

// Sci-Fi Colors
const SCI_FI_CYAN = '#00ffff';
const SCI_FI_MAGENTA = '#ff00ff';
const SCI_FI_GREEN = '#90ee90';
const BG_GRID_COLOR = 'rgba(0, 255, 255, 0.1)';
const TICKS_COLOR = SCI_FI_GREEN;
const LABEL_COLOR = '#e5e7eb'; // Chart labels

// Global Chart Options Setup (To ensure consistency)
Chart.defaults.color = LABEL_COLOR;
Chart.defaults.font.family = 'Share Tech Mono, monospace'; // <<< FIX: เปลี่ยนฟอนต์หลักของ Chart เป็น Share Tech Mono

// Monthly (Line)
(() => {
    const {labels, values} = toXY(monthly, 'ym', 'net_sales');
    new Chart(document.getElementById('chartMonthly'), {
      type: 'line',
      data: { labels, datasets: [{ 
          label: 'NET SALES (฿)', 
          data: values, 
          tension: .4, 
          fill: true,
          backgroundColor: 'rgba(0, 255, 255, 0.2)', 
          borderColor: SCI_FI_CYAN, 
          pointBackgroundColor: SCI_FI_CYAN,
          pointRadius: 3,
          pointHoverRadius: 5
      }] },
      options: { 
          plugins: { legend: { labels: { color: LABEL_COLOR } } }, 
          scales: {
            x: { ticks: { color: TICKS_COLOR }, grid: { color: BG_GRID_COLOR } },
            y: { ticks: { color: TICKS_COLOR }, grid: { color: BG_GRID_COLOR } }
          }
      }
    });
})();

// Category (Doughnut)
(() => {
    const {labels, values} = toXY(category, 'category', 'net_sales');
    const backgroundColors = [SCI_FI_MAGENTA, SCI_FI_CYAN, SCI_FI_GREEN, '#fcee09', '#0077ff', '#ff55aa', '#ff5500', '#00ff55'];
    new Chart(document.getElementById('chartCategory'), {
      type: 'doughnut',
      data: { labels, datasets: [{ 
          data: values,
          backgroundColor: backgroundColors,
          borderColor: '#00000a', 
          borderWidth: 2
      }] },
      options: { plugins: { legend: { position: 'bottom', labels: { color: LABEL_COLOR } } } }
    });
})();

// Top products (Horizontal Bar)
(() => {
    const labels = topProducts.map(o => o.product_name);
    const qty = topProducts.map(o => parseInt(o.qty_sold));
    new Chart(document.getElementById('chartTopProducts'), {
      type: 'bar',
      data: { labels, datasets: [{ 
          label: 'UNITS SOLD', 
          data: qty,
          backgroundColor: SCI_FI_GREEN
      }] },
      options: {
        indexAxis: 'y',
        plugins: { legend: { labels: { color: LABEL_COLOR } } },
        scales: {
          x: { ticks: { color: TICKS_COLOR }, grid: { color: BG_GRID_COLOR } },
          y: { ticks: { color: TICKS_COLOR }, grid: { color: BG_GRID_COLOR } }
        }
      }
    });
})();

// Region (Bar)
(() => {
    const {labels, values} = toXY(region, 'region', 'net_sales');
    new Chart(document.getElementById('chartRegion'), {
      type: 'bar',
      data: { labels, datasets: [{ 
          label: 'NET SALES (฿)', 
          data: values,
          backgroundColor: SCI_FI_MAGENTA
      }] },
      options: { plugins: { legend: { labels: { color: LABEL_COLOR } } }, scales: {
        x: { ticks: { color: TICKS_COLOR }, grid: { color: BG_GRID_COLOR } },
        y: { ticks: { color: TICKS_COLOR }, grid: { color: BG_GRID_COLOR } }
      }}
    });
})();

// Payment (Pie)
(() => {
    const {labels, values} = toXY(payment, 'payment_method', 'net_sales');
    const backgroundColors = [SCI_FI_CYAN, SCI_FI_MAGENTA, SCI_FI_GREEN, '#fcee09', '#0077ff', '#ff55aa', '#ff5500', '#00ff55'];
    new Chart(document.getElementById('chartPayment'), {
      type: 'pie',
      data: { labels, datasets: [{ 
          data: values,
          backgroundColor: backgroundColors,
          borderColor: '#00000a',
          borderWidth: 2
      }] },
      options: { plugins: { legend: { position: 'bottom', labels: { color: LABEL_COLOR } } } }
    });
})();

// Hourly (Bar)
(() => {
    const {labels, values} = toXY(hourly, 'hour_of_day', 'net_sales');
    new Chart(document.getElementById('chartHourly'), {
      type: 'bar',
      data: { labels, datasets: [{ 
          label: 'NET SALES (฿)', 
          data: values,
          backgroundColor: SCI_FI_CYAN
      }] },
      options: { plugins: { legend: { labels: { color: LABEL_COLOR } } }, scales: {
        x: { ticks: { color: TICKS_COLOR }, grid: { color: BG_GRID_COLOR } },
        y: { ticks: { color: TICKS_COLOR }, grid: { color: BG_GRID_COLOR } }
      }}
    });
})();

// New vs Returning (Line)
(() => {
    const labels = newReturning.map(o => o.date_key);
    const newC = newReturning.map(o => parseFloat(o.new_customer_sales));
    const retC = newReturning.map(o => parseFloat(o.returning_sales));
    new Chart(document.getElementById('chartNewReturning'), {
      type: 'line',
      data: { labels,
        datasets: [
          { 
              label: 'NEW CUSTOMER SALES (฿)', 
              data: newC, 
              tension: .4, 
              fill: false,
              borderColor: SCI_FI_GREEN,
              pointBackgroundColor: SCI_FI_GREEN,
              pointRadius: 2,
              pointHoverRadius: 4
          },
          { 
              label: 'RETURNING SALES (฿)', 
              data: retC, 
              tension: .4, 
              fill: false,
              borderColor: SCI_FI_MAGENTA,
              pointBackgroundColor: SCI_FI_MAGENTA,
              pointRadius: 2,
              pointHoverRadius: 4
          }
        ]
      },
      options: { plugins: { legend: { labels: { color: LABEL_COLOR } } }, scales: {
        x: { ticks: { color: TICKS_COLOR, maxTicksLimit: 12 }, grid: { color: BG_GRID_COLOR } },
        y: { ticks: { color: TICKS_COLOR }, grid: { color: BG_GRID_COLOR } }
      }}
    });
})();
</script>

</body>
</html>