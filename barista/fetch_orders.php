<?php
include '../db.php';

try {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE status = 'Pending' ORDER BY created_at DESC");
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($orders as &$order) {
        $stmt2 = $pdo->prepare("SELECT oi.*, p.name AS product_name 
                                FROM order_items oi 
                                JOIN products p ON oi.product_id = p.id 
                                WHERE oi.order_id = ?");
        $stmt2->execute([$order['id']]);
        $order['items'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    }

    if (empty($orders)) {
        echo "<p>No orders available.</p>";
    } else {
        foreach ($orders as $order) {
            echo '<div class="card order-card" data-order-id="' . $order['id'] . '">';
            echo '<div class="card-header">';
            echo 'Order ID: ' . $order['id'] . ' &nbsp;|&nbsp; Customer: ' . htmlspecialchars($order['customer_name']);
            echo '<span class="float-end">' . date('g:i A', strtotime($order['created_at'])) . '</span>';
            echo '</div>';
            echo '<div class="card-body">';
            echo '<ul class="list-group list-group-flush">';
            foreach ($order['items'] as $item) {
                echo '<li class="list-group-item">';
                echo htmlspecialchars($item['product_name']);
                if (!empty($item['size'])) {
                    echo " (" . htmlspecialchars($item['size']);
                    if (!empty($item['temperature'])) {
                        echo ", " . htmlspecialchars($item['temperature']);
                    }
                    echo ")";
                }
                echo " x" . htmlspecialchars($item['quantity']);
                echo '</li>';
            }
            echo '</ul>';
            echo '</div>';
            echo '<div class="card-footer text-end">';
            echo '<button class="btn btn-success btn-sm mark-ready" data-order-id="' . $order['id'] . '">Mark as Ready</button>';
            echo '</div>';
            echo '</div>';
        }
    }
} catch (PDOException $e) {
    echo "<p>Error fetching orders: " . $e->getMessage() . "</p>";
}
?>