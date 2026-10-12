<?php
session_start();

$products = [
    'ballpen' => ['name' => 'Ballpen', 'price' => 12],
    'pencil' => ['name' => 'Pencil', 'price' => 10],
    'notebook' => ['name' => 'Notebook', 'price' => 35],
    'yellow-pad' => ['name' => 'Yellow Pad', 'price' => 45],
    'eraser' => ['name' => 'Eraser', 'price' => 8],
    'ruler' => ['name' => 'Ruler', 'price' => 15],
];

$_SESSION['cart'] ??= [];
$_SESSION['receipt'] ??= null;

function peso(int|float $amount): string
{
    return '₱' . number_format($amount, 2);
}

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $productId = $_POST['product_id'] ?? '';

    if ($action === 'add' && isset($products[$productId])) {
        $_SESSION['cart'][$productId] = ($_SESSION['cart'][$productId] ?? 0) + 1;
        $_SESSION['receipt'] = null;
        unset($_SESSION['payment_error'], $_SESSION['payment_input']);
    } elseif (in_array($action, ['increase', 'decrease', 'remove'], true) && isset($products[$productId], $_SESSION['cart'][$productId])) {
        if ($action === 'increase') {
            $_SESSION['cart'][$productId]++;
        } elseif ($action === 'decrease') {
            $_SESSION['cart'][$productId]--;
            if ($_SESSION['cart'][$productId] <= 0) {
                unset($_SESSION['cart'][$productId]);
            }
        } else {
            unset($_SESSION['cart'][$productId]);
        }
        $_SESSION['receipt'] = null;
        unset($_SESSION['payment_error'], $_SESSION['payment_input']);
    } elseif ($action === 'confirm') {
        $paymentInput = trim((string) ($_POST['amount_paid'] ?? ''));
        $total = 0;
        foreach ($_SESSION['cart'] as $id => $quantity) {
            if (isset($products[$id])) {
                $total += $products[$id]['price'] * $quantity;
            }
        }

        $error = '';
        if ($paymentInput === '') {
            $error = 'Please enter the amount paid.';
        } elseif (!preg_match('/^-?(?:\d{1,9}(?:\.\d{1,2})?|\.\d{1,2})$/D', $paymentInput)) {
            $error = 'Enter a valid amount using up to two decimal places.';
        } elseif ($paymentInput[0] === '-') {
            $error = 'Payment cannot be negative.';
        } elseif ($total <= 0) {
            $error = 'Add at least one product before confirming payment.';
        } else {
            $paid = (float) $paymentInput;
            if ($paid < $total) {
                $error = 'The amount paid is less than the total amount due.';
            }
        }

        if ($error !== '') {
            $_SESSION['payment_error'] = $error;
            $_SESSION['payment_input'] = $paymentInput;
        } else {
            $items = [];
            foreach ($_SESSION['cart'] as $id => $quantity) {
                if (isset($products[$id])) {
                    $items[] = [
                        'name' => $products[$id]['name'],
                        'price' => $products[$id]['price'],
                        'quantity' => $quantity,
                        'subtotal' => $products[$id]['price'] * $quantity,
                    ];
                }
            }
            $_SESSION['receipt'] = [
                'reference' => 'SS-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2))),
                'created_at' => date('F j, Y · g:i A'),
                'items' => $items,
                'total' => $total,
                'paid' => $paid,
                'change' => $paid - $total,
            ];
            unset($_SESSION['payment_error'], $_SESSION['payment_input']);
        }
    } elseif ($action === 'new_transaction') {
        $_SESSION['cart'] = [];
        $_SESSION['receipt'] = null;
        unset($_SESSION['payment_error'], $_SESSION['payment_input']);
    }

    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

$cart = $_SESSION['cart'];
$receipt = $_SESSION['receipt'];
$paymentError = $_SESSION['payment_error'] ?? '';
$paymentInput = $_SESSION['payment_input'] ?? '';
unset($_SESSION['payment_error'], $_SESSION['payment_input']);

$cartCount = array_sum($cart);
$total = 0;
foreach ($cart as $id => $quantity) {
    if (isset($products[$id])) {
        $total += $products[$id]['price'] * $quantity;
    }
}
$icons = ['ballpen' => '✒️', 'pencil' => '✏️', 'notebook' => '📓', 'yellow-pad' => '🗒️', 'eraser' => '🧽', 'ruler' => '📏'];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student Supplies | Order Desk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --ink: #172a3a; --muted: #728092; --brand: #176b5b; --canvas: #f4f7f6; }
        body { background: var(--canvas); color: var(--ink); font-family: Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; }
        .topbar { background: #fff; border-bottom: 1px solid #e7ece9; }
        .brand-mark { width: 42px; height: 42px; border-radius: 13px; display: grid; place-items: center; background: #e5f3ee; font-size: 1.3rem; }
        .eyebrow { color: var(--brand); font-size: .72rem; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }
        .hero { background: radial-gradient(circle at 85% 20%, #daf0e8 0, transparent 28%), linear-gradient(120deg, #fff, #f0f8f4); border: 1px solid #e5eeea; border-radius: 24px; }
        .panel { background: #fff; border: 1px solid #e7ece9; border-radius: 20px; box-shadow: 0 10px 30px rgba(27, 53, 44, .035); }
        .section-title { font-weight: 800; letter-spacing: -.025em; }
        .product-card { height: 100%; border: 1px solid #e8eeeb; border-radius: 17px; transition: transform .18s ease, box-shadow .18s ease; }
        .product-card:hover { transform: translateY(-3px); box-shadow: 0 12px 25px rgba(27, 53, 44, .08); }
        .product-icon { width: 52px; height: 52px; display: grid; place-items: center; border-radius: 16px; background: #f0f6f3; font-size: 1.55rem; }
        .price { color: var(--brand); font-weight: 800; }
        .btn-brand { --bs-btn-bg: var(--brand); --bs-btn-border-color: var(--brand); --bs-btn-hover-bg: #105548; --bs-btn-hover-border-color: #105548; --bs-btn-color: #fff; --bs-btn-hover-color: #fff; font-weight: 700; }
        .qty-control { display: inline-flex; align-items: center; gap: .65rem; }
        .qty-control form { display: inline; }
        .qty-button { width: 28px; height: 28px; display: inline-grid; place-items: center; padding: 0; border: 1px solid #dfe8e3; border-radius: 8px; background: white; color: var(--ink); font-weight: 800; }
        .summary-total { border-top: 1px dashed #d9e3de; }
        .receipt-ticket { border: 1px dashed #aac6b8; background: #fbfefc; border-radius: 15px; }
        .receipt-row + .receipt-row { border-top: 1px solid #edf2ef; }
        .text-muted-custom { color: var(--muted); }
        @media print { .topbar, .hero, .products-section, .cart-section, .summary-section, .payment-section, .new-transaction, footer { display: none !important; } body { background: white; } .receipt-section { display: block !important; } .panel { box-shadow: none; } }
    </style>
</head>
<body>
<header class="topbar">
    <div class="container py-3 d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-3">
            <div class="brand-mark">🎒</div>
            <div><div class="fw-bold">Supply Desk</div><div class="small text-muted-custom">Student Supplies Ordering</div></div>
        </div>
        <span class="badge rounded-pill text-bg-light border px-3 py-2">Cashier checkout</span>
    </div>
</header>
<main class="container py-4 py-lg-5">
    <section class="hero p-4 p-lg-5 mb-4 mb-lg-5">
        <div class="row align-items-center g-3">
            <div class="col-lg-8">
                <div class="eyebrow mb-2">Everything for class</div>
                <h1 class="display-6 fw-bold mb-2">utog</h1>
                <p class="text-muted-custom mb-0">Choose your essentials, review your order, and check out with cash.</p>
            </div>
            <div class="col-lg-4 text-lg-end"><span class="badge rounded-pill bg-white text-success border px-3 py-2">6 everyday essentials</span></div>
        </div>
    </section>

    <?php if ($paymentError !== ''): ?>
        <div class="alert alert-danger border-0 shadow-sm" role="alert"><strong>Payment not completed.</strong> <?= escape($paymentError) ?></div>
    <?php endif; ?>

    <div class="row g-4 align-items-start">
        <section class="col-lg-7 products-section">
            <div class="d-flex justify-content-between align-items-end mb-3">
                <div><div class="eyebrow">Shop</div><h2 class="h4 section-title mb-0">Product list</h2></div>
                <span class="small text-muted-custom">Prices in Philippine pesos</span>
            </div>
            <div class="row row-cols-1 row-cols-sm-2 g-3">
                <?php foreach ($products as $id => $product): ?>
                    <div class="col">
                        <article class="product-card bg-white p-3">
                            <div class="d-flex align-items-start justify-content-between mb-3">
                                <div class="product-icon"><?= $icons[$id] ?></div>
                                <span class="badge rounded-pill text-bg-light">School essential</span>
                            </div>
                            <div class="d-flex align-items-center justify-content-between gap-2">
                                <div><h3 class="h6 fw-bold mb-1"><?= escape($product['name']) ?></h3><div class="price"><?= peso($product['price']) ?></div></div>
                                <form method="post">
                                    <input type="hidden" name="action" value="add">
                                    <input type="hidden" name="product_id" value="<?= escape($id) ?>">
                                    <button class="btn btn-brand btn-sm rounded-3 px-3" type="submit">Add to cart <span aria-hidden="true">+</span></button>
                                </form>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <aside class="col-lg-5">
            <section class="panel p-4 cart-section mb-4">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div><div class="eyebrow">Your selection</div><h2 class="h5 section-title mb-0">Shopping cart</h2></div>
                    <span class="badge rounded-pill text-bg-light border"><?= (int) $cartCount ?> items</span>
                </div>
                <?php if ($cart === []): ?>
                    <div class="text-center py-4"><div class="fs-2 mb-2">🛍️</div><p class="text-muted-custom mb-0">Your cart is empty. Add a product to get started.</p></div>
                <?php else: ?>
                    <div class="vstack gap-3">
                        <?php foreach ($cart as $id => $quantity): if (!isset($products[$id])) continue; $product = $products[$id]; $lineTotal = $product['price'] * $quantity; ?>
                            <div class="d-flex justify-content-between align-items-center gap-2">
                                <div class="flex-grow-1">
                                    <div class="fw-semibold"><?= escape($product['name']) ?></div>
                                    <div class="small text-muted-custom"><?= peso($product['price']) ?> each · <?= peso($lineTotal) ?></div>
                                </div>
                                <div class="text-end">
                                    <div class="qty-control mb-1">
                                        <form method="post"><input type="hidden" name="action" value="decrease"><input type="hidden" name="product_id" value="<?= escape($id) ?>"><button class="qty-button" type="submit" aria-label="Decrease <?= escape($product['name']) ?> quantity">−</button></form>
                                        <span class="fw-bold"><?= (int) $quantity ?></span>
                                        <form method="post"><input type="hidden" name="action" value="increase"><input type="hidden" name="product_id" value="<?= escape($id) ?>"><button class="qty-button" type="submit" aria-label="Increase <?= escape($product['name']) ?> quantity">+</button></form>
                                    </div>
                                    <form method="post"><input type="hidden" name="action" value="remove"><input type="hidden" name="product_id" value="<?= escape($id) ?>"><button class="btn btn-link btn-sm text-danger p-0 text-decoration-none" type="submit">Remove</button></form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="panel p-4 summary-section mb-4">
                <div class="eyebrow">Checkout</div><h2 class="h5 section-title mb-3">Order summary</h2>
                <div class="d-flex justify-content-between text-muted-custom mb-2"><span>Items</span><span><?= (int) $cartCount ?></span></div>
                <div class="d-flex justify-content-between summary-total pt-3 mt-3"><span class="fw-bold">Total amount</span><span class="h5 fw-bold text-success mb-0"><?= peso($total) ?></span></div>
            </section>

            <section class="panel p-4 payment-section mb-4">
                <div class="eyebrow">Payment</div><h2 class="h5 section-title mb-3">Cash payment</h2>
                <form method="post">
                    <input type="hidden" name="action" value="confirm">
                    <label class="form-label small fw-semibold" for="amount_paid">Amount paid</label>
                    <div class="input-group mb-2"><span class="input-group-text bg-light">₱</span><input class="form-control" id="amount_paid" name="amount_paid" type="text" inputmode="decimal" placeholder="0.00" value="<?= escape($paymentInput) ?>" aria-describedby="payment-hint"></div>
                    <div id="payment-hint" class="form-text mb-3">Enter a non-negative amount with up to two decimal places.</div>
                    <button type="submit" class="btn btn-brand w-100 rounded-3" <?= ($total <= 0 || $receipt !== null) ? 'disabled' : '' ?>>Confirm payment</button>
                </form>
            </section>

            <?php if ($receipt !== null): ?>
                <section class="panel p-4 receipt-section mb-4" aria-live="polite">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div><div class="eyebrow">Payment confirmed</div><h2 class="h5 section-title mb-0">Digital receipt</h2></div>
                        <span class="fs-4">✅</span>
                    </div>
                    <div class="receipt-ticket p-3">
                        <div class="small text-muted-custom mb-1">Transaction reference</div><div class="fw-bold mb-1"><?= escape($receipt['reference']) ?></div>
                        <div class="small text-muted-custom mb-3"><?= escape($receipt['created_at']) ?></div>
                        <div class="receipt-row py-2 small fw-semibold d-flex justify-content-between"><span>Product / quantity</span><span>Subtotal</span></div>
                        <?php foreach ($receipt['items'] as $item): ?>
                            <div class="receipt-row py-2 small d-flex justify-content-between gap-2"><span><?= escape($item['name']) ?> × <?= (int) $item['quantity'] ?><span class="d-block text-muted-custom"><?= peso($item['price']) ?> each</span></span><span class="fw-semibold text-nowrap"><?= peso($item['subtotal']) ?></span></div>
                        <?php endforeach; ?>
                        <div class="receipt-row pt-3 mt-1 d-flex justify-content-between"><span>Total due</span><strong><?= peso($receipt['total']) ?></strong></div>
                        <div class="d-flex justify-content-between mt-2"><span>Amount paid</span><strong><?= peso($receipt['paid']) ?></strong></div>
                        <div class="d-flex justify-content-between mt-2 text-success"><span>Change</span><strong><?= peso($receipt['change']) ?></strong></div>
                        <div class="alert alert-success py-2 mt-3 mb-0 small">Payment received. Thank you for shopping!</div>
                    </div>
                    <div class="d-flex gap-2 mt-3">
                        <button class="btn btn-outline-secondary rounded-3 flex-fill" type="button" onclick="window.print()">Print receipt</button>
                        <form method="post" class="flex-fill new-transaction"><input type="hidden" name="action" value="new_transaction"><button class="btn btn-brand rounded-3 w-100" type="submit">New transaction</button></form>
                    </div>
                </section>
            <?php endif; ?>
        </aside>
    </div>
</main>
<footer class="container py-4 text-center small text-muted-custom">Simple, secure checkout for your school-day essentials.</footer>
</body>
</html>
