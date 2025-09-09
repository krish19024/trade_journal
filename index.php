<?php
include "db.php";

// ---- Theme Toggle (via cookie) ----
if (isset($_GET['theme'])) {
    setcookie("theme", $_GET['theme'], time() + (86400*30), "/");
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}
$theme = $_COOKIE['theme'] ?? 'dark';

// ---- Handle Form Submission (PRG) ----
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $date = $_POST['trade_date'];
    $stock = $_POST['stock_name'];
    $qty   = $_POST['quantity'];
    $buy   = $_POST['buying_price'];
    $sell  = $_POST['selling_price'];
    $emotions = implode(", ", $_POST['emotions'] ?? []);
    $strategy = implode(", ", $_POST['strategy'] ?? []);
    $notes = $_POST['notes'];

    $sql = "INSERT INTO trades (trade_date, stock_name, quantity, buying_price, selling_price, emotions, strategy, notes) 
            VALUES ('$date','$stock','$qty','$buy','$sell','$emotions','$strategy','$notes')";
    mysqli_query($conn, $sql);

    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

// ---- Search & Sort ----
$search   = $_GET['search'] ?? '';
$sort_col = $_GET['sort'] ?? 'trade_date';
$sort_dir = $_GET['dir'] ?? 'DESC';
$allowed_cols = ['trade_date','stock_name','quantity','buying_price','selling_price'];
if (!in_array($sort_col, $allowed_cols)) $sort_col = 'trade_date';
$sort_dir = ($sort_dir==='ASC')?'ASC':'DESC';

$where = '';
if ($search) {
    $s = $conn->real_escape_string($search);
    $where = "WHERE stock_name LIKE '%$s%' OR emotions LIKE '%$s%' OR strategy LIKE '%$s%' OR notes LIKE '%$s%'";
}

// ---- Fetch Trades ----
$result = $conn->query("SELECT * FROM trades $where ORDER BY $sort_col $sort_dir");

// ---- KPIs ----
$totalTrades=0; $totalPnL=0; $win=0;
$rows=[];
while ($r=$result->fetch_assoc()) {
    $pnl = ($r['selling_price'] - $r['buying_price'])*$r['quantity'];
    $r['pnl']=$pnl;
    $rows[]=$r;
    $totalTrades++;
    $totalPnL += $pnl;
    if ($pnl>0) $win++;
}
$winRate = $totalTrades? round($win/$totalTrades*100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Trade Journal</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
<style>
/* === same CSS you gave === */
<?php include "style.css"; ?>
</style>
</head>
<body class="<?= $theme ?>">
    <!-- Navbar -->
    <div class="navbar">
        <div class="brand"><div class="logo"></div>Trade Journal</div>
        <div class="nav-actions">
            <?php if($theme==='dark'): ?>
                <a class="btn" href="?theme=light">☀️ Light</a>
            <?php else: ?>
                <a class="btn" href="?theme=dark">🌙 Dark</a>
            <?php endif; ?>
            <a href="#form" class="btn primary">➕ Add Trade</a>
        </div>
    </div>

    <!-- Main -->
    <main class="main">
        <!-- KPIs -->
        <section class="card half" id="summary">
            <div class="grid">
                <div class="card third"><div class="muted">Total Trades</div><div class="kpi"><?= $totalTrades ?></div></div>
                <div class="card third"><div class="muted">Total P&amp;L</div>
                    <div class="kpi" style="color:<?= $totalPnL>=0?'var(--success)':'var(--danger)' ?>">
                        <?= ($totalPnL>=0?'+':'').$totalPnL ?>
                    </div></div>
                <div class="card third"><div class="muted">Win Rate</div><div class="kpi"><?= $winRate ?>%</div></div>
            </div>
        </section>

        <!-- Form -->
        <section class="card" id="form">
            <h2>➕ Add Trade</h2>
            <form method="post">
                <div class="form-grid">
                    <div class="field sm-4"><label>Date</label><input type="date" name="trade_date" required></div>
                    <div class="field sm-4"><label>Stock Name</label><input type="text" name="stock_name" required></div>
                    <div class="field sm-4"><label>Quantity</label><input type="number" name="quantity" required></div>
                    <div class="field sm-6"><label>Buying Price</label><input type="number" step="0.01" name="buying_price" required></div>
                    <div class="field sm-6"><label>Selling Price</label><input type="number" step="0.01" name="selling_price" required></div>
                    <div class="field sm-6">
                        <label>Emotions</label>
                        <select name="emotions[]" multiple required>
                            <option>Fear</option><option>Anxious</option><option>Sad</option>
                            <option>Happy</option><option>Confident</option><option>Greed</option><option>Revenge</option>
                        </select>
                    </div>
                    <div class="field sm-6">
                        <label>Strategy</label>
                        <div class="checks">
                            <label><input type="checkbox" name="strategy[]" value="Breakout"> Breakout</label>
                            <label><input type="checkbox" name="strategy[]" value="Pullback"> Pullback</label>
                            <label><input type="checkbox" name="strategy[]" value="Crossover"> Crossover</label>
                        </div>
                    </div>
                    <div class="field"><label>Notes</label><textarea name="notes" rows="3"></textarea></div>
                </div>
                <div class="submit-row">
                    <button type="reset" class="btn">Reset</button>
                    <button type="submit" class="btn primary">Save</button>
                </div>
            </form>
        </section>

        <!-- Records -->
        <section class="card" id="records">
            <h2>📊 Records</h2>
            <form method="get">
                <input type="text" name="search" placeholder="Search…" value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn">Filter</button>
                <a href="export.php?search=<?= urlencode($search) ?>" class="btn">⬇️ Export CSV</a>
            </form>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th><a href="?sort=trade_date&dir=<?= $sort_dir==='ASC'?'DESC':'ASC' ?>">Date</a></th>
                            <th><a href="?sort=stock_name&dir=<?= $sort_dir==='ASC'?'DESC':'ASC' ?>">Stock</a></th>
                            <th><a href="?sort=quantity&dir=<?= $sort_dir==='ASC'?'DESC':'ASC' ?>">Qty</a></th>
                            <th><a href="?sort=buying_price&dir=<?= $sort_dir==='ASC'?'DESC':'ASC' ?>">Buy</a></th>
                            <th><a href="?sort=selling_price&dir=<?= $sort_dir==='ASC'?'DESC':'ASC' ?>">Sell</a></th>
                            <th>P&L</th>
                            <th>Emotions</th><th>Strategy</th><th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <body class="dark">   
                        <?php foreach($rows as $r): ?>
                        <tr>
                            <td><?= $r['trade_date'] ?></td>
                            <td><?= $r['stock_name'] ?></td>
                            <td><?= $r['quantity'] ?></td>
                            <td><?= $r['buying_price'] ?></td>
                            <td><?= $r['selling_price'] ?></td>
                            <td><span class="pill <?= $r['pnl']>=0?'success':'danger' ?>"><?= ($r['pnl']>=0?'+':'').$r['pnl'] ?></span></td>
                            <td><?php foreach(explode(',',$r['emotions']) as $e) echo "<span class='badge'>".trim($e)."</span> "; ?></td>
                            <td><?php foreach(explode(',',$r['strategy']) as $s) echo "<span class='badge'>".trim($s)."</span> "; ?></td>
                            <td><?= $r['notes'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>
