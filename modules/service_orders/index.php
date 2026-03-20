<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

require_once('../../config/database.php');
require_once('../../config/app.php');
$conn = $database->getConnection();

// Verificar permissão de visualização
requirePermission('service_orders', 'view');

// Endpoint AJAX: retorna lista de clientes atualizada
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_customers') {
    header('Content-Type: application/json');
    $stmt = $conn->query("SELECT id, name, cpf_cnpj, phone FROM customers WHERE deleted_at IS NULL ORDER BY name ASC");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// Endpoint AJAX: cadastro rápido de cliente dentro da OS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'quick_add_customer') {
    header('Content-Type: application/json');
    try {
        $stmt = $conn->prepare("INSERT INTO customers
            (name, cpf_cnpj, phone, email, address, city, state, zipcode, notes, birth_date, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->execute([
            $_POST['name'],
            $_POST['cpf_cnpj'] ?: null,
            $_POST['phone'] ?: null,
            $_POST['email'] ?: null,
            $_POST['address'] ?: null,
            $_POST['city'] ?: null,
            $_POST['state'] ?: null,
            $_POST['zipcode'] ?: null,
            $_POST['notes'] ?: null,
            $_POST['birth_date'] ?: null,
        ]);
        $newId = $conn->lastInsertId();
        echo json_encode(['success' => true, 'id' => $newId, 'name' => $_POST['name'], 'cpf_cnpj' => $_POST['cpf_cnpj'] ?? '', 'phone' => $_POST['phone'] ?? '']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// =============================
// 🔧 CRUD
// =============================

// Criar OS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'add') {
    requirePermission('service_orders', 'create');
    try {
        // Upload de foto
        $imageName = null;
        if (!empty($_FILES['os_image']['name']) && $_FILES['os_image']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['os_image']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (in_array($ext, $allowed)) {
                $imageName = uniqid('os_') . '.' . $ext;
                move_uploaded_file($_FILES['os_image']['tmp_name'], __DIR__ . '/../../uploads/service_orders/' . $imageName);
            }
        }

        // Processar formas de pagamento
        $paymentMethodsJson = $_POST['payment_methods'] ?? '[]';
        $paymentMethodsArr = json_decode($paymentMethodsJson, true) ?: [];
        $primaryMethod = !empty($paymentMethodsArr) ? $paymentMethodsArr[0]['method'] : null;
        $primaryInstallments = !empty($paymentMethodsArr) ? ($paymentMethodsArr[0]['installments'] ?? 1) : 1;

        $stmt = $conn->prepare("INSERT INTO service_orders
            (customer_id, user_id, device, device_type, status, total_cost, discount, payment_method, payment_methods, installments, change_amount, deposit_amount, entry_datetime,
             technical_report, reported_problem, customer_observations, internal_observations,
             device_powers_on, device_password, password_pattern,
             checklist_lens, checklist_lens_condition, checklist_back_cover, checklist_screen,
             checklist_connector, checklist_camera_front_back, checklist_face_id, checklist_sim_card,
             technician_name, attendant_name, warranty_period, image)
            VALUES (?, ?, ?, ?, 'open', ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['customer_id'],
            $_SESSION['user_id'],
            $_POST['device'],
            $_POST['device_type'] ?? 'celular',
            $_POST['total_cost'] ?? 0,
            $_POST['discount'] ?? 0,
            $primaryMethod,
            $paymentMethodsJson,
            $primaryInstallments,
            $_POST['change_amount'] ?? 0,
            $_POST['deposit_amount'] ?? 0,
            $_POST['technical_report'] ?? null,
            $_POST['reported_problem'] ?? null,
            $_POST['customer_observations'] ?? null,
            $_POST['internal_observations'] ?? null,
            $_POST['device_powers_on'] ?? 'sim',
            $_POST['device_password'] ?? null,
            $_POST['password_pattern'] ?? null,
            isset($_POST['checklist_lens']) ? 1 : 0,
            null,
            isset($_POST['checklist_back_cover']) ? '1' : null,
            isset($_POST['checklist_screen']) ? 1 : 0,
            isset($_POST['checklist_connector']) ? 1 : 0,
            isset($_POST['checklist_camera_front_back']) ? '1' : null,
            isset($_POST['checklist_face_id']) ? 1 : 0,
            isset($_POST['checklist_sim_card']) ? 1 : 0,
            $_POST['technician_name'] ?? null,
            $_POST['attendant_name'] ?? null,
            $_POST['warranty_period'] ?? '90 dias',
            $imageName
        ]);

        // Pegar ID da OS criada
        $osId = $conn->lastInsertId();
        // Buscar nome do cliente para o log
        $stmtCustName = $conn->prepare("SELECT name FROM customers WHERE id=?");
        $stmtCustName->execute([$_POST['customer_id']]);
        $custNameLog = $stmtCustName->fetchColumn() ?: '?';

        // Salvar itens (produtos + serviços)
        if (!empty($_POST['products_data'])) {
            $itemsData = json_decode($_POST['products_data'], true);

            if (is_array($itemsData) && count($itemsData) > 0) {
                $stmtItem = $conn->prepare("INSERT INTO service_order_items
                    (service_order_id, type, product_id, description, quantity, unit_price, total_price)
                    VALUES (?, ?, ?, ?, ?, ?, ?)");

                $stmtUpdateStock = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");

                foreach ($itemsData as $item) {
                    $type = $item['type'] ?? 'product';
                    $productId = !empty($item['id']) ? $item['id'] : null;
                    $description = $item['name'] ?? '';

                    $stmtItem->execute([
                        $osId,
                        $type,
                        $productId,
                        $description,
                        $item['quantity'],
                        $item['price'],
                        $item['subtotal']
                    ]);

                    // Atualizar estoque apenas para produtos
                    if ($type === 'product' && $productId) {
                        $stmtUpdateStock->execute([$item['quantity'], $productId]);
                    }
                }
            }
        }

        $logNewValuesOS = [
            'Cliente'        => $custNameLog,
            'Dispositivo'    => $_POST['device'] ?? '',
            'Marca/Modelo'   => ($_POST['brand'] ?? '') . ' ' . ($_POST['model'] ?? ''),
            'Status'         => $_POST['status'] ?? '',
            'Total'          => formatMoney($_POST['total_cost'] ?? 0),
            'Pagamento'      => $_POST['payment_method'] ?? '',
            'Técnico'        => $_POST['technician_name'] ?? '',
        ];
        logActivity('create', 'service_orders', $osId,
            "OS #$osId criada — Cliente: $custNameLog, Dispositivo: {$_POST['device']}, Total: " . formatMoney($_POST['total_cost'] ?? 0),
            null, $logNewValuesOS
        );
        echo "<script>sessionStorage.setItem('fl_flash', JSON.stringify({msg:'Ordem de serviço criada com sucesso!',type:'success'}));window.location='index.php?print_os=$osId';</script>";
        exit;
    } catch (PDOException $e) {
        echo "<script>document.addEventListener('DOMContentLoaded',function(){ showAlert('Erro ao cadastrar: " . addslashes($e->getMessage()) . "','error'); });</script>";
    }
}

// Editar OS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'edit') {
    requirePermission('service_orders', 'edit');
    try {
        // Buscar status anterior da OS
        $stmtOld = $conn->prepare("SELECT status, total_cost, payment_method, image FROM service_orders WHERE id = ?");
        $stmtOld->execute([$_POST['id']]);
        $oldOrder = $stmtOld->fetch(PDO::FETCH_ASSOC);

        // Bloquear edição se a OS já foi entregue
        if ($oldOrder['status'] === 'delivered') {
            echo "<script>sessionStorage.setItem('fl_flash', JSON.stringify({msg:'⚠️ Ordem de Serviço já foi ENTREGUE e não pode mais ser editada!',type:'warning'}));window.location='index.php';</script>";
            exit;
        }

        // Upload de foto
        $imageName = $oldOrder['image'];
        if (!empty($_POST['remove_os_image']) && $imageName) {
            $oldPath = __DIR__ . '/../../uploads/service_orders/' . $imageName;
            if (file_exists($oldPath)) unlink($oldPath);
            $imageName = null;
        }
        if (!empty($_FILES['os_image']['name']) && $_FILES['os_image']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['os_image']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (in_array($ext, $allowed)) {
                if ($oldOrder['image']) {
                    $oldPath = __DIR__ . '/../../uploads/service_orders/' . $oldOrder['image'];
                    if (file_exists($oldPath)) unlink($oldPath);
                }
                $imageName = uniqid('os_') . '.' . $ext;
                move_uploaded_file($_FILES['os_image']['tmp_name'], __DIR__ . '/../../uploads/service_orders/' . $imageName);
            }
        }

        // Data de saída: registrar quando status muda para "delivered"
        $exitDatetime = ($oldOrder['status'] !== 'delivered' && $_POST['status'] === 'delivered') ? date('Y-m-d H:i:s') : null;

        // Processar formas de pagamento
        $paymentMethodsJson = $_POST['payment_methods'] ?? '[]';
        $paymentMethodsArr = json_decode($paymentMethodsJson, true) ?: [];
        $primaryMethod = !empty($paymentMethodsArr) ? $paymentMethodsArr[0]['method'] : null;
        $primaryInstallments = !empty($paymentMethodsArr) ? ($paymentMethodsArr[0]['installments'] ?? 1) : 1;

        $stmt = $conn->prepare("UPDATE service_orders
            SET customer_id=?, device=?, device_type=?, status=?, total_cost=?, discount=?, payment_method=?, payment_methods=?, installments=?, change_amount=?, deposit_amount=?,
                technical_report=?, reported_problem=?, customer_observations=?, internal_observations=?,
                device_powers_on=?, device_password=?, password_pattern=?,
                checklist_lens=?, checklist_lens_condition=?, checklist_back_cover=?, checklist_screen=?,
                checklist_connector=?, checklist_camera_front_back=?, checklist_face_id=?, checklist_sim_card=?,
                technician_name=?, attendant_name=?, warranty_period=?, image=?, exit_datetime=COALESCE(?, exit_datetime)
            WHERE id=?");
        $stmt->execute([
            $_POST['customer_id'],
            $_POST['device'],
            $_POST['device_type'] ?? 'celular',
            $_POST['status'],
            $_POST['total_cost'] ?? 0,
            $_POST['discount'] ?? 0,
            $primaryMethod,
            $paymentMethodsJson,
            $primaryInstallments,
            $_POST['change_amount'] ?? 0,
            $_POST['deposit_amount'] ?? 0,
            $_POST['technical_report'] ?? null,
            $_POST['reported_problem'] ?? null,
            $_POST['customer_observations'] ?? null,
            $_POST['internal_observations'] ?? null,
            $_POST['device_powers_on'] ?? 'sim',
            $_POST['device_password'] ?? null,
            $_POST['password_pattern'] ?? null,
            isset($_POST['checklist_lens']) ? 1 : 0,
            null,
            isset($_POST['checklist_back_cover']) ? '1' : null,
            isset($_POST['checklist_screen']) ? 1 : 0,
            isset($_POST['checklist_connector']) ? 1 : 0,
            isset($_POST['checklist_camera_front_back']) ? '1' : null,
            isset($_POST['checklist_face_id']) ? 1 : 0,
            isset($_POST['checklist_sim_card']) ? 1 : 0,
            $_POST['technician_name'] ?? null,
            $_POST['attendant_name'] ?? null,
            $_POST['warranty_period'] ?? '90 dias',
            $imageName,
            $exitDatetime,
            $_POST['id']
        ]);

        // Se status mudou para "delivered" (Entregue), registrar no caixa
        if ($oldOrder['status'] !== 'delivered' && $_POST['status'] === 'delivered') {
            $totalCost = $_POST['total_cost'] ?? 0;
            $paymentMethod = $_POST['payment_method'] ?? 'dinheiro';
            $changeAmount = $_POST['change_amount'] ?? 0;

            // Só registra no caixa se tiver valor maior que zero
            if ($totalCost > 0) {
                // VERIFICAR SE JÁ EXISTE REGISTRO NO CAIXA PARA ESSA O.S.
                // (evita duplicação quando O.S. é reaberta e fechada novamente)
                $stmtCheck = $conn->prepare("SELECT COUNT(*) as total FROM cash_flow
                    WHERE reference_id = ? AND reference_type IN ('service_order', 'service_order_change')");
                $stmtCheck->execute([$_POST['id']]);
                $existeRegistro = $stmtCheck->fetch(PDO::FETCH_ASSOC)['total'] > 0;

                // Só registra se ainda NÃO existe registro no caixa
                if (!$existeRegistro) {
                    $description = "OS #" . $_POST['id'] . " - " . $_POST['device'] . " - " . ucfirst($paymentMethod);

                    // Se for pagamento em dinheiro com troco
                    if ($paymentMethod === 'dinheiro' && $changeAmount > 0) {
                        $valorRecebido = $totalCost + $changeAmount;

                        // Registrar ENTRADA: Valor recebido do cliente (positivo)
                        $stmtCash = $conn->prepare("INSERT INTO cash_flow
                            (user_id, type, description, amount, reference_id, reference_type, created_at)
                            VALUES (?, 'service', ?, ?, ?, 'service_order', NOW())");
                        $stmtCash->execute([
                            $_SESSION['user_id'],
                            $description . " - Valor Recebido",
                            $valorRecebido,
                            $_POST['id']
                        ]);

                        // Registrar SAÍDA: Troco devolvido (negativo como expense)
                        $stmtCash = $conn->prepare("INSERT INTO cash_flow
                            (user_id, type, description, amount, reference_id, reference_type, created_at)
                            VALUES (?, 'expense', ?, ?, ?, 'service_order_change', NOW())");
                        $stmtCash->execute([
                            $_SESSION['user_id'],
                            $description . " - Troco",
                            $changeAmount,
                            $_POST['id']
                        ]);
                    } else {
                        // Pagamento sem troco (PIX, Cartão, etc) - registra valor total
                        $stmtCash = $conn->prepare("INSERT INTO cash_flow
                            (user_id, type, description, amount, reference_id, reference_type, created_at)
                            VALUES (?, 'service', ?, ?, ?, 'service_order', NOW())");
                        $stmtCash->execute([
                            $_SESSION['user_id'],
                            $description,
                            $totalCost,
                            $_POST['id']
                        ]);
                    }
                }
            }
        }

        // Se status mudou para "cancelled", devolver estoque dos produtos
        if ($oldOrder['status'] !== 'cancelled' && $_POST['status'] === 'cancelled') {
            $stmtCancelItems = $conn->prepare("SELECT product_id, quantity, type FROM service_order_items WHERE service_order_id = ?");
            $stmtCancelItems->execute([$_POST['id']]);
            $cancelItems = $stmtCancelItems->fetchAll(PDO::FETCH_ASSOC);
            foreach ($cancelItems as $ci) {
                if ($ci['type'] === 'product' && $ci['product_id']) {
                    $conn->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?")->execute([$ci['quantity'], $ci['product_id']]);
                }
            }
        }

        // Se status saiu de "cancelled" para outro, descontar estoque novamente
        if ($oldOrder['status'] === 'cancelled' && $_POST['status'] !== 'cancelled') {
            $stmtReactivateItems = $conn->prepare("SELECT product_id, quantity, type FROM service_order_items WHERE service_order_id = ?");
            $stmtReactivateItems->execute([$_POST['id']]);
            $reactivateItems = $stmtReactivateItems->fetchAll(PDO::FETCH_ASSOC);
            foreach ($reactivateItems as $ri) {
                if ($ri['type'] === 'product' && $ri['product_id']) {
                    $conn->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?")->execute([$ri['quantity'], $ri['product_id']]);
                }
            }
        }

        // Atualizar itens (produtos + serviços)
        if (!empty($_POST['products_data'])) {
            $itemsData = json_decode($_POST['products_data'], true);

            // Devolver estoque dos produtos antigos
            $stmtOldItems = $conn->prepare("SELECT product_id, quantity, type FROM service_order_items WHERE service_order_id = ?");
            $stmtOldItems->execute([$_POST['id']]);
            $oldItems = $stmtOldItems->fetchAll(PDO::FETCH_ASSOC);

            foreach ($oldItems as $oldItem) {
                if ($oldItem['type'] === 'product' && $oldItem['product_id']) {
                    $stmtReturnStock = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?");
                    $stmtReturnStock->execute([$oldItem['quantity'], $oldItem['product_id']]);
                }
            }

            // Remover todos os itens antigos
            $stmtDeleteItems = $conn->prepare("DELETE FROM service_order_items WHERE service_order_id = ?");
            $stmtDeleteItems->execute([$_POST['id']]);

            // Adicionar novos itens
            if (is_array($itemsData) && count($itemsData) > 0) {
                $stmtItem = $conn->prepare("INSERT INTO service_order_items
                    (service_order_id, type, product_id, description, quantity, unit_price, total_price)
                    VALUES (?, ?, ?, ?, ?, ?, ?)");

                $stmtUpdateStock = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");

                foreach ($itemsData as $item) {
                    $type = $item['type'] ?? 'product';
                    $productId = !empty($item['id']) ? $item['id'] : null;
                    $description = $item['name'] ?? '';

                    $stmtItem->execute([
                        $_POST['id'],
                        $type,
                        $productId,
                        $description,
                        $item['quantity'],
                        $item['price'],
                        $item['subtotal']
                    ]);

                    if ($type === 'product' && $productId) {
                        $stmtUpdateStock->execute([$item['quantity'], $productId]);
                    }
                }
            }
        }

        $statusLabels = ['open' => 'Aberta', 'in_progress' => 'Em andamento', 'completed' => 'Concluída', 'delivered' => 'Entregue', 'cancelled' => 'Cancelada'];
        logActivity('update', 'service_orders', (int)$_POST['id'],
            "OS #{$_POST['id']} editada — Status: " . ($statusLabels[$_POST['status']] ?? $_POST['status']) . ", Total: " . formatMoney($_POST['total_cost'] ?? 0),
            ['status' => $statusLabels[$oldOrder['status']] ?? $oldOrder['status'], 'total' => formatMoney($oldOrder['total_cost']), 'pagamento' => $oldOrder['payment_method']],
            ['status' => $statusLabels[$_POST['status']] ?? $_POST['status'], 'total' => formatMoney($_POST['total_cost'] ?? 0), 'pagamento' => $primaryMethod]
        );
        echo "<script>sessionStorage.setItem('fl_flash', JSON.stringify({msg:'Ordem de serviço atualizada com sucesso!',type:'success'}));window.location='index.php';</script>";
        exit;
    } catch (PDOException $e) {
        echo "<script>document.addEventListener('DOMContentLoaded',function(){ showAlert('Erro ao atualizar: " . addslashes($e->getMessage()) . "','error'); });</script>";
    }
}

// Excluir OS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'delete') {
    requirePermission('service_orders', 'delete');
    $id = (int) $_POST['id'];

    // Verificar se a OS já foi entregue
    $stmtCheck = $conn->prepare("SELECT status FROM service_orders WHERE id = ?");
    $stmtCheck->execute([$id]);
    $order = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if ($order && $order['status'] === 'delivered') {
        echo "<script>sessionStorage.setItem('fl_flash', JSON.stringify({msg:'⚠️ Não é possível excluir uma OS que já foi ENTREGUE!',type:'warning'}));window.location='index.php';</script>";
        exit;
    }

    // Devolver estoque dos produtos antes de excluir
    $stmtItems = $conn->prepare("SELECT product_id, quantity, type FROM service_order_items WHERE service_order_id = ?");
    $stmtItems->execute([$id]);
    $osItems = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
    foreach ($osItems as $osItem) {
        if ($osItem['type'] === 'product' && $osItem['product_id']) {
            $conn->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?")->execute([$osItem['quantity'], $osItem['product_id']]);
        }
    }

    $conn->prepare("DELETE FROM service_order_items WHERE service_order_id = ?")->execute([$id]);
    $stmt = $conn->prepare("DELETE FROM service_orders WHERE id = ?");
    $stmt->execute([$id]);
    echo "<script>sessionStorage.setItem('fl_flash', JSON.stringify({msg:'Ordem de serviço excluída com sucesso! Estoque devolvido.',type:'success'}));window.location='index.php';</script>";
    exit;
}

// Desbloquear OS (requer admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'unlock') {
    // Limpar qualquer output anterior
    ob_clean();

    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $osId = (int) ($_POST['os_id'] ?? 0);

    // Verificar se usuário é admin (buscar por email)
    $stmt = $conn->prepare("SELECT id, password FROM users WHERE email = ? AND role = 'admin'");
    $stmt->execute([$username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin && password_verify($password, $admin['password'])) {
        // Admin válido, desbloquear OS (mudar status de 'delivered' para 'completed')
        $stmtUnlock = $conn->prepare("UPDATE service_orders SET status = 'completed' WHERE id = ?");
        $stmtUnlock->execute([$osId]);

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'OS desbloqueada com sucesso!']);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Credenciais de admin inválidas!']);
    }
    exit;
}

// =============================
// 🔍 LISTAGEM E FILTRO
// =============================
$search = $_GET['search'] ?? '';
$filter_status = $_GET['status'] ?? '';
$where = "WHERE 1=1";
$params = [];

if (!empty($search)) {
    $where .= " AND (c.name LIKE ? OR so.device LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($filter_status)) {
    $where .= " AND so.status = ?";
    $params[] = $filter_status;
}

// =============================
// 📄 PAGINAÇÃO
// =============================
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;

// Validar valores permitidos de registros por página
$allowedPerPage = [10, 20, 30, 50, 100];
if (!in_array($perPage, $allowedPerPage)) {
    $perPage = 20;
}

$offset = ($page - 1) * $perPage;

// Contar total de registros
$stmtCount = $conn->prepare("
    SELECT COUNT(*) as total
    FROM service_orders so
    LEFT JOIN customers c ON so.customer_id = c.id
    $where
");
$stmtCount->execute($params);
$totalRecords = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalRecords / $perPage);

// Buscar registros com paginação
$paramsWithPagination = array_merge($params, [$perPage, $offset]);
$stmt = $conn->prepare("
    SELECT so.*, c.name as customer_name, c.phone as customer_phone
    FROM service_orders so
    LEFT JOIN customers c ON so.customer_id = c.id AND c.deleted_at IS NULL
    $where
    ORDER BY so.id DESC
    LIMIT ? OFFSET ?
");
$stmt->execute($paramsWithPagination);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Verificar se deve perguntar sobre impressão após criar OS
$printOsData = null;
if (!empty($_GET['print_os']) && is_numeric($_GET['print_os'])) {
    $stmtPrint = $conn->prepare("
        SELECT so.*, c.name as customer_name, c.cpf_cnpj, c.phone as customer_phone
        FROM service_orders so
        LEFT JOIN customers c ON so.customer_id = c.id
        WHERE so.id = ?
    ");
    $stmtPrint->execute([(int)$_GET['print_os']]);
    $printOsData = $stmtPrint->fetch(PDO::FETCH_ASSOC);
}

// Buscar clientes para o formulário (apenas clientes ativos)
$customers = $conn->query("SELECT id, name, cpf_cnpj, phone FROM customers WHERE deleted_at IS NULL ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Buscar técnicos e atendentes cadastrados
$technicians = $conn->query("SELECT id, name FROM technicians WHERE active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$attendants = $conn->query("SELECT id, name FROM attendants WHERE active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Buscar produtos para o formulário
$products = $conn->query("SELECT id, name, sale_price as price, stock_quantity, COALESCE(type,'product') as type, allow_price_edit FROM products WHERE active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// =============================
// 📊 ESTATÍSTICAS
// =============================
$stats = $conn->query("
    SELECT
        COUNT(*) as total_orders,
        COUNT(CASE WHEN status = 'open' THEN 1 END) as awaiting,
        COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
        COUNT(CASE WHEN status = 'invoiced' THEN 1 END) as invoiced,
        COUNT(CASE WHEN status = 'delivered' THEN 1 END) as delivered,
        COALESCE(SUM(total_cost), 0) as total_revenue
    FROM service_orders
")->fetch(PDO::FETCH_ASSOC);

// =============================
// 🎨 FUNÇÕES AUXILIARES
// =============================
function traduzirStatus($status) {
    $map = [
        'open' => 'Aguardando',
        'in_progress' => 'Em Andamento',
        'completed' => 'Concluído',
        'invoiced' => 'Faturada',
        'delivered' => 'Entregue',
        'cancelled' => 'Cancelado'
    ];
    return $map[$status] ?? ucfirst($status);
}

// formatMoney já está definido em app.php
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ordem de Serviço - FL Reparos</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
body {
    margin:0;
    font-family:'Segoe UI', Tahoma, sans-serif;
    background:linear-gradient(135deg,#667eea,#764ba2);
    padding:20px;
    min-height:100vh;
}
.container {max-width:1400px;margin:auto;}
.header, .search-box, .stats-box {
    background:rgba(255,255,255,0.9);
    backdrop-filter:blur(10px);
    padding:20px;
    border-radius:15px;
    box-shadow:0 8px 32px rgba(0,0,0,0.15);
    margin-bottom:25px;
}
.header {display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;}
.header h1 {font-size:1.8rem;display:flex;align-items:center;gap:10px;margin:0;}
.btn {
    border:none;border-radius:8px;padding:10px 20px;color:#fff;font-weight:500;
    cursor:pointer;display:inline-flex;align-items:center;gap:8px;transition:all .3s;
    text-decoration:none;
}
.btn-primary {background:linear-gradient(45deg,#4CAF50,#45a049);}
.btn-secondary {background:linear-gradient(45deg,#2196F3,#1976D2);}
.btn-danger {background:linear-gradient(45deg,#f44336,#d32f2f);}
.btn-warning {background:linear-gradient(45deg,#FF9800,#F57C00);}
.btn:hover {transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,0.2);}
.btn-sm {padding:6px 12px;font-size:0.85rem;}

.stats-grid {
    display:grid;
    grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));
    gap:15px;
}
.stat-card {
    background:linear-gradient(135deg, rgba(255,255,255,0.9), rgba(255,255,255,0.7));
    padding:15px;
    border-radius:12px;
    box-shadow:0 4px 15px rgba(0,0,0,0.1);
    text-align:center;
}
.stat-card .icon {
    font-size:2rem;
    margin-bottom:8px;
}
.stat-card .value {
    font-size:1.5rem;
    font-weight:bold;
    margin:5px 0;
}
.stat-card .label {
    color:#666;
    font-size:0.85rem;
    text-transform:uppercase;
}
.stat-card.blue .icon {color:#2196F3;}
.stat-card.orange .icon {color:#FF9800;}
.stat-card.green .icon {color:#4CAF50;}
.stat-card.purple .icon {color:#9C27B0;}

.table {
    width:100%;border-collapse:collapse;background:rgba(255,255,255,0.95);
    border-radius:15px;overflow:hidden;box-shadow:0 8px 32px rgba(0,0,0,0.1);
}
th,td {padding:12px;text-align:left;}
th {background:linear-gradient(45deg,#f8f9fa,#e9ecef);font-weight:600;}
tr:hover {background:rgba(103,58,183,0.1);}
.badge {
    padding:5px 10px;border-radius:8px;color:white;font-weight:bold;text-transform:capitalize;
    font-size:0.85rem;display:inline-block;
}
.open {background:#FF9800;color:#333;}
.in_progress {background:#2196F3;}
.completed {background:#4CAF50;}
.invoiced {background:#00BCD4;}
.delivered {background:#9C27B0;}
.cancelled {background:#f44336;}

/* Senha Desenho (Pattern Lock) */
.pattern-lock-container {display:inline-block;}
.pattern-grid-input {display:grid;grid-template-columns:repeat(3,1fr);gap:8px;width:120px;padding:10px;background:#f9f9f9;border:2px solid #ddd;border-radius:8px;}
.pattern-dot {width:30px;height:30px;border:3px solid #999;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .2s;user-select:none;}
.pattern-dot span {font-size:10px;color:#999;font-weight:bold;}
.pattern-dot.active {background:#667eea;border-color:#667eea;}
.pattern-dot.active span {color:#fff;}

.modal {
    display:none;position:fixed;top:0;left:0;width:100%;height:100%;
    background:rgba(0,0,0,0.6);z-index:1000;backdrop-filter:blur(5px);
    overflow-y:auto;
}
.modal-content {
    background:rgba(255,255,255,0.95);margin:40px auto;padding:30px;
    border-radius:15px;max-width:700px;box-shadow:0 8px 32px rgba(0,0,0,0.2);
}
.modal-header {display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;}
.modal-header h2 {margin:0;display:flex;align-items:center;gap:10px;}
.close {
    background:#f44336;border:none;color:#fff;border-radius:50%;
    width:35px;height:35px;cursor:pointer;font-size:18px;transition:all .3s;
}
.close:hover {transform:rotate(90deg);background:#d32f2f;}
.form-row {display:flex;gap:12px;margin-bottom:10px;flex-wrap:wrap;}
.form-group {flex:1;min-width:0;}
.form-group label {display:block;margin-bottom:5px;font-weight:600;color:#333;}
.form-control {
    width:100%;padding:10px;border:2px solid #ddd;border-radius:8px;
    font-size:1rem;transition:border .3s;
}
.form-control:focus {border-color:#667eea;outline:none;box-shadow:0 0 0 3px rgba(102,126,234,0.1);}
#createModal .form-control, #editModal .form-control {padding:7px 10px;}
.empty {text-align:center;padding:40px;color:#777;font-style:italic;}

.filter-box {
    display:flex;gap:30px;align-items:flex-end;flex-wrap:wrap;
}
.filter-box .form-group {
    flex:1;min-width:200px;
}

/* Paginação */
.pagination-container {
    background:rgba(255,255,255,0.9);
    backdrop-filter:blur(10px);
    padding:15px 20px;
    border-radius:15px;
    box-shadow:0 8px 32px rgba(0,0,0,0.15);
    margin-bottom:20px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    flex-wrap:wrap;
    gap:15px;
}
.pagination-info {
    color:#666;
    font-size:0.9rem;
    font-weight:500;
}
.pagination-controls {
    display:flex;
    gap:20px;
    align-items:center;
    flex-wrap:wrap;
}
.per-page-selector {
    display:flex;
    gap:10px;
    align-items:center;
}
.per-page-selector label {
    color:#666;
    font-size:0.9rem;
    font-weight:500;
}
.per-page-selector select {
    padding:8px 12px;
    border:2px solid #ddd;
    border-radius:8px;
    background:white;
    font-size:0.9rem;
    cursor:pointer;
    transition:all 0.3s;
}
.per-page-selector select:hover {
    border-color:#667eea;
}
.per-page-selector select:focus {
    border-color:#667eea;
    outline:none;
    box-shadow:0 0 0 3px rgba(102,126,234,0.1);
}
.pagination-buttons {
    display:flex;
    gap:10px;
    align-items:center;
}
.pagination-btn {
    padding:8px 15px;
    border:2px solid #ddd;
    border-radius:8px;
    background:white;
    color:#667eea;
    text-decoration:none;
    font-weight:500;
    transition:all 0.3s;
    display:inline-flex;
    align-items:center;
    gap:5px;
    cursor:pointer;
}
.pagination-btn:hover:not(.disabled) {
    background:linear-gradient(45deg,#667eea,#764ba2);
    color:white;
    border-color:transparent;
    transform:translateY(-2px);
    box-shadow:0 4px 12px rgba(102,126,234,0.3);
}
.pagination-btn.disabled {
    background:#f5f5f5;
    color:#ccc;
    border-color:#f0f0f0;
    cursor:not-allowed;
}
.page-info {
    color:#666;
    font-weight:500;
    font-size:0.9rem;
}
.os-autocomplete-suggestions {
    position:absolute;top:100%;left:0;right:0;background:#fff;
    border:2px solid #667eea;border-top:none;max-height:220px;overflow-y:auto;
    z-index:2000;border-radius:0 0 10px 10px;box-shadow:0 8px 20px rgba(0,0,0,0.15);
}
.os-autocomplete-suggestions .os-suggestion {
    padding:10px 12px;cursor:pointer;border-bottom:1px solid #eee;transition:background 0.2s;
}
.os-autocomplete-suggestions .os-suggestion:hover {
    background:linear-gradient(45deg,rgba(102,126,234,0.1),rgba(118,75,162,0.1));
}
.os-autocomplete-suggestions .os-suggestion .details {
    display:flex;gap:10px;color:#888;font-size:12px;margin-top:2px;
}
.os-autocomplete-suggestions .os-no-results {
    padding:12px;color:#999;text-align:center;font-style:italic;
}
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1><i class="fas fa-wrench"></i> Ordem de Serviço</h1>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <button class="btn btn-primary" onclick="openModal('createModal')"><i class="fas fa-plus"></i> Nova OS</button>
            <a href="../../index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
        </div>
    </div>

    <div class="stats-box">
        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="icon"><i class="fas fa-clipboard-list"></i></div>
                <div class="value"><?= $stats['total_orders'] ?></div>
                <div class="label">Total de OS</div>
            </div>
            <div class="stat-card orange">
                <div class="icon"><i class="fas fa-clock"></i></div>
                <div class="value"><?= $stats['awaiting'] ?></div>
                <div class="label">Aguardando</div>
            </div>
            <div class="stat-card blue">
                <div class="icon"><i class="fas fa-tools"></i></div>
                <div class="value"><?= $stats['in_progress'] ?></div>
                <div class="label">Em Andamento</div>
            </div>
            <div class="stat-card green">
                <div class="icon"><i class="fas fa-check-circle"></i></div>
                <div class="value"><?= $stats['delivered'] ?></div>
                <div class="label">Entregue</div>
            </div>
            <div class="stat-card purple">
                <div class="icon"><i class="fas fa-dollar-sign"></i></div>
                <div class="value"><?= formatMoney($stats['total_revenue']) ?></div>
                <div class="label">Receita Total</div>
            </div>
        </div>
    </div>

    <div class="search-box">
        <form method="GET">
            <div class="filter-box">
                <div class="form-group">
                    <label>Buscar</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Cliente, aparelho...">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <option value="">Todos</option>
                        <option value="open" <?= $filter_status === 'open' ? 'selected' : '' ?>>Aguardando</option>
                        <option value="in_progress" <?= $filter_status === 'in_progress' ? 'selected' : '' ?>>Em Andamento</option>
                        <option value="completed" <?= $filter_status === 'completed' ? 'selected' : '' ?>>Concluído</option>
                        <option value="invoiced" <?= $filter_status === 'invoiced' ? 'selected' : '' ?>>Faturada</option>
                        <option value="delivered" <?= $filter_status === 'delivered' ? 'selected' : '' ?>>Entregue</option>
                        <option value="cancelled" <?= $filter_status === 'cancelled' ? 'selected' : '' ?>>Cancelado</option>
                    </select>
                </div>
                <div class="form-group" style="flex:0 0 auto;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filtrar</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Paginação -->
    <div class="pagination-container">
        <div class="pagination-info">
            Mostrando <?= min($offset + 1, $totalRecords) ?> a <?= min($offset + $perPage, $totalRecords) ?> de <?= $totalRecords ?> registros
        </div>

        <div class="pagination-controls">
            <div class="per-page-selector">
                <label>Registros por página:</label>
                <select onchange="changePerPage(this.value)">
                    <?php foreach($allowedPerPage as $option): ?>
                        <option value="<?= $option ?>" <?= $perPage == $option ? 'selected' : '' ?>><?= $option ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="pagination-buttons">
                <?php if($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&per_page=<?= $perPage ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($filter_status) ? '&status=' . urlencode($filter_status) : '' ?>" class="pagination-btn">
                        <i class="fas fa-chevron-left"></i> Anterior
                    </a>
                <?php else: ?>
                    <span class="pagination-btn disabled"><i class="fas fa-chevron-left"></i> Anterior</span>
                <?php endif; ?>

                <span class="page-info">Página <?= $page ?> de <?= max(1, $totalPages) ?></span>

                <?php if($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>&per_page=<?= $perPage ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($filter_status) ? '&status=' . urlencode($filter_status) : '' ?>" class="pagination-btn">
                        Próxima <i class="fas fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="pagination-btn disabled">Próxima <i class="fas fa-chevron-right"></i></span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>OS#</th>
                <th>Cliente</th>
                <th>Aparelho</th>
                <th>Status</th>
                <th>Valor (R$)</th>
                <th>Data</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($orders)): ?>
                <tr><td colspan="7" class="empty">Nenhuma ordem de serviço cadastrada.</td></tr>
            <?php else: foreach($orders as $row): ?>
            <tr>
                <td><strong>#<?= $row['id'] ?></strong></td>
                <td>
                    <strong><?= htmlspecialchars($row['customer_name'] ?? 'N/A') ?></strong>
                    <?php if(!empty($row['customer_phone'])): ?>
                        <br><small style="color:#666;"><i class="fas fa-phone"></i> <?= htmlspecialchars($row['customer_phone']) ?></small>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($row['device']) ?></td>
                <td><span class="badge <?= $row['status'] ?>"><?= traduzirStatus($row['status']) ?></span></td>
                <td><strong><?= formatMoney($row['total_cost']) ?></strong></td>
                <td><?= date('d/m/Y', strtotime($row['entry_datetime'])) ?></td>
                <td>
                    <button class="btn btn-primary btn-sm" onclick='printServiceOrder(<?= json_encode($row) ?>)' title="Imprimir">
                        <i class="fas fa-print"></i>
                    </button>
                    <?php if ($row['status'] !== 'delivered'): ?>
                        <?php if (hasPermission('service_orders', 'edit')): ?>
                        <button class="btn btn-warning btn-sm" onclick='openEditModal(<?= json_encode($row) ?>)' title="Editar">
                            <i class="fas fa-edit"></i>
                        </button>
                        <?php endif; ?>
                        <?php if (hasPermission('service_orders', 'delete')): ?>
                        <form method="POST" onsubmit="return false;" style="display:inline;" id="deleteOSForm_<?= $row['id'] ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                            <button type="button" class="btn btn-danger btn-sm" title="Excluir" onclick="showConfirm('Excluir esta OS?','Excluir','Excluir','Cancelar','danger').then(ok=>{if(ok)document.getElementById('deleteOSForm_<?= $row['id'] ?>').submit();})"><i class="fas fa-trash-alt"></i></button>
                        </form>
                        <?php endif; ?>
                    <?php else: ?>
                    <button class="btn btn-secondary btn-sm" onclick="openUnlockModal(<?= $row['id'] ?>)" title="Desbloquear OS (requer admin)">
                        <i class="fas fa-lock"></i>
                    </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<!-- MODAL NOVA OS v2 -->
<div id="createModal" class="modal">
    <div class="modal-content" style="max-width:95vw;width:1300px;">
        <div class="modal-header">
            <h2><i class="fas fa-plus-circle"></i> Nova Ordem de Serviço</h2>
            <button class="close" onclick="confirmCloseOS('createModal')">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add">

            <!-- ROW 1: Cliente + Tipo + Modelo -->
            <div style="display:flex;gap:30px;margin-bottom:8px;align-items:flex-end;">
                <div style="flex:0 0 320px;">
                    <label style="font-size:12px;font-weight:600;margin-bottom:3px;display:block;">Cliente * <a href="#" onclick="openQuickCustomerModal(event)" style="font-size:11px;color:#00b894;margin-left:6px;"><i class="fas fa-plus-circle"></i> Cadastrar novo</a></label>
                    <div style="position:relative;">
                        <input type="text" id="createCustomerSearch" class="form-control" placeholder="Buscar por nome, CPF ou telefone..." autocomplete="off" style="font-size:13px;">
                        <input type="hidden" name="customer_id" id="createCustomerId" required>
                        <div id="createCustomerSuggestions" class="os-autocomplete-suggestions" style="display:none;"></div>
                    </div>
                </div>
                <div style="flex:0 0 160px;">
                    <label style="font-size:12px;font-weight:600;margin-bottom:3px;display:block;">Tipo de Produto</label>
                    <select name="device_type" class="form-control" style="font-size:13px;">
                        <option value="celular">Celular</option>
                        <option value="tablet">Tablet</option>
                        <option value="notebook">Notebook</option>
                        <option value="smartwatch">Smartwatch</option>
                        <option value="console">Console</option>
                        <option value="outro">Outro</option>
                    </select>
                </div>
                <div style="flex:0 0 350px;">
                    <label style="font-size:12px;font-weight:600;margin-bottom:3px;display:block;">Modelo do Aparelho *</label>
                    <input type="text" name="device" class="form-control" required placeholder="Ex: iPhone 13 Pro" style="font-size:13px;">
                </div>
            </div>

            <!-- ROW 2: 4 textareas side by side -->
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:30px;margin-bottom:10px;">
                <div>
                    <label style="font-size:12px;font-weight:600;margin-bottom:3px;display:block;">Problema Relatado</label>
                    <textarea name="reported_problem" class="form-control" rows="3" style="font-size:12px;resize:vertical;"></textarea>
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;margin-bottom:3px;display:block;">Laudo Técnico</label>
                    <textarea name="technical_report" class="form-control" rows="3" style="font-size:12px;resize:vertical;"></textarea>
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;margin-bottom:3px;display:block;">Observações para o Cliente</label>
                    <textarea name="customer_observations" class="form-control" rows="3" style="font-size:12px;resize:vertical;"></textarea>
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;margin-bottom:3px;display:block;">Observações Internas</label>
                    <textarea name="internal_observations" class="form-control" rows="3" style="font-size:12px;resize:vertical;background:#fff9e6;"></textarea>
                </div>
            </div>

            <!-- TABS -->
            <div style="margin-bottom:0;">
                <div style="display:flex;gap:0;border-bottom:2px solid #667eea;">
                    <button type="button" id="create_tab_products_btn" onclick="switchTab('create','products')" style="padding:7px 18px;border:none;background:linear-gradient(45deg,#667eea,#764ba2);color:white;font-weight:600;font-size:13px;border-radius:6px 6px 0 0;cursor:pointer;">
                        <i class="fas fa-box"></i> Produtos e Serviços
                    </button>
                    <button type="button" id="create_tab_payment_btn" onclick="switchTab('create','payment')" style="padding:7px 18px;border:none;background:#e9ecef;color:#666;font-weight:600;font-size:13px;border-radius:6px 6px 0 0;cursor:pointer;margin-left:4px;">
                        <i class="fas fa-dollar-sign"></i> Pagamento
                    </button>
                </div>

                <!-- TAB: Produtos e Serviços -->
                <div id="create_tab_products" style="display:flex;gap:10px;padding:10px;background:#fafafa;border:1px solid #e0e0e0;border-top:none;border-radius:0 0 8px 8px;">
                    <!-- LEFT: products/services -->
                    <div style="flex:1;min-width:0;">
                        <!-- Adicionar Produto -->
                        <div style="background:#f8f9ff;padding:10px;border-radius:8px;margin-bottom:8px;">
                            <strong style="color:#667eea;font-size:12px;"><i class="fas fa-box"></i> Adicionar Produto</strong>
                            <div style="display:grid;grid-template-columns:6fr 1.5fr 1fr 1.5fr;gap:30px;margin-top:8px;align-items:end;">
                                <div style="position:relative;">
                                    <label style="font-size:11px;font-weight:600;display:block;margin-bottom:4px;">Buscar Produto</label>
                                    <input type="text" id="create_product_search" class="form-control" placeholder="Digite o nome do produto..." autocomplete="off" oninput="searchProducts('create')" style="font-size:12px;">
                                    <div id="create_product_results" style="display:none;position:absolute;top:100%;left:0;right:0;background:white;border:1px solid #ddd;border-radius:8px;max-height:200px;overflow-y:auto;z-index:1000;box-shadow:0 4px 6px rgba(0,0,0,0.1);"></div>
                                    <input type="hidden" id="create_selected_product_id">
                                    <input type="hidden" id="create_selected_product_name">
                                    <input type="hidden" id="create_selected_product_stock">
                                </div>
                                <div>
                                    <label style="font-size:11px;font-weight:600;display:block;margin-bottom:4px;">Preço Unit. (R$)</label>
                                    <input type="number" step="0.01" id="create_product_price_input" class="form-control" placeholder="0,00" style="font-size:12px;">
                                </div>
                                <div>
                                    <label style="font-size:11px;font-weight:600;display:block;margin-bottom:4px;">Qtd</label>
                                    <input type="number" id="create_product_quantity" class="form-control" value="1" min="1" style="font-size:12px;">
                                </div>
                                <div style="display:flex;flex-direction:column;">
                                    <label style="font-size:11px;font-weight:600;display:block;margin-bottom:4px;visibility:hidden;">.</label>
                                    <button type="button" class="btn btn-primary" onclick="addProductToOS('create')" style="width:100%;font-size:12px;flex:1;">
                                        <i class="fas fa-plus"></i> Adicionar
                                    </button>
                                </div>
                            </div>
                        </div>
                        <!-- Adicionar Serviço -->
                        <div style="background:#f0fff0;padding:10px;border-radius:8px;margin-bottom:8px;">
                            <strong style="color:#4CAF50;font-size:12px;"><i class="fas fa-tools"></i> Adicionar Serviço</strong>
                            <div style="display:grid;grid-template-columns:6fr 1.5fr 1fr 1.5fr;gap:30px;margin-top:8px;align-items:end;">
                                <div style="position:relative;">
                                    <label style="font-size:11px;font-weight:600;display:block;margin-bottom:4px;">Buscar Serviço</label>
                                    <input type="text" id="create_service_search" class="form-control" placeholder="Digite o nome do serviço..." autocomplete="off" oninput="searchServices('create')" style="font-size:12px;">
                                    <div id="create_service_results" style="display:none;position:absolute;top:100%;left:0;right:0;background:white;border:1px solid #ddd;border-radius:8px;max-height:200px;overflow-y:auto;z-index:1000;box-shadow:0 4px 6px rgba(0,0,0,0.1);"></div>
                                    <input type="hidden" id="create_selected_service_id">
                                    <input type="hidden" id="create_selected_service_name">
                                </div>
                                <div>
                                    <label style="font-size:11px;font-weight:600;display:block;margin-bottom:4px;">Preço (R$)</label>
                                    <input type="number" step="0.01" id="create_service_price" class="form-control" placeholder="0,00" style="font-size:12px;">
                                </div>
                                <div>
                                    <label style="font-size:11px;font-weight:600;display:block;margin-bottom:4px;">Qtd</label>
                                    <input type="number" id="create_service_quantity" class="form-control" value="1" min="1" style="font-size:12px;">
                                </div>
                                <div style="display:flex;flex-direction:column;">
                                    <label style="font-size:11px;font-weight:600;display:block;margin-bottom:4px;visibility:hidden;">.</label>
                                    <button type="button" class="btn btn-primary" onclick="addServiceToOS('create')" style="width:100%;font-size:12px;flex:1;background:linear-gradient(45deg,#4CAF50,#45a049);">
                                        <i class="fas fa-plus"></i> Adicionar
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div id="create_products_list"></div>
                        <input type="hidden" id="create_products_data" name="products_data" value="[]">
                    </div>
                    <!-- RIGHT SIDEBAR: Checklist + Device info -->
                    <div style="flex:0 0 240px;background:white;border:1px solid #e0e0e0;border-radius:8px;padding:10px;">
                        <div style="font-size:12px;font-weight:700;color:#667eea;margin-bottom:8px;border-bottom:1px solid #f0f0f0;padding-bottom:5px;"><i class="fas fa-clipboard-check"></i> Checklist</div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:5px;margin-bottom:10px;">
                            <label style="display:flex;align-items:center;gap:5px;font-size:12px;cursor:pointer;"><input type="checkbox" name="checklist_lens" value="1"> Lente</label>
                            <label style="display:flex;align-items:center;gap:5px;font-size:12px;cursor:pointer;"><input type="checkbox" name="checklist_screen" value="1"> Tela Trincada</label>
                            <label style="display:flex;align-items:center;gap:5px;font-size:12px;cursor:pointer;"><input type="checkbox" name="checklist_sim_card" value="1"> Chip</label>
                            <label style="display:flex;align-items:center;gap:5px;font-size:12px;cursor:pointer;"><input type="checkbox" name="checklist_back_cover" value="1"> Tampa Traseira</label>
                            <label style="display:flex;align-items:center;gap:5px;font-size:12px;cursor:pointer;"><input type="checkbox" name="checklist_face_id" value="1"> Sem Face ID</label>
                            <label style="display:flex;align-items:center;gap:5px;font-size:12px;cursor:pointer;"><input type="checkbox" name="checklist_connector" value="1"> Conector</label>
                            <label style="display:flex;align-items:center;gap:5px;font-size:12px;cursor:pointer;"><input type="checkbox" name="checklist_camera_front_back" value="1"> Câmera</label>
                        </div>
                        <div style="font-size:12px;font-weight:700;color:#667eea;margin-bottom:6px;border-bottom:1px solid #f0f0f0;padding-bottom:5px;"><i class="fas fa-mobile-alt"></i> Aparelho</div>
                        <div style="margin-bottom:8px;">
                            <label style="font-size:11px;font-weight:600;display:block;margin-bottom:3px;">O Aparelho Liga?</label>
                            <select name="device_powers_on" class="form-control" style="font-size:12px;padding:4px;">
                                <option value="sim">Sim</option>
                                <option value="nao">Não</option>
                            </select>
                        </div>
                        <div style="margin-bottom:8px;">
                            <label style="font-size:11px;font-weight:600;display:block;margin-bottom:3px;">Senha do Aparelho</label>
                            <input type="text" name="device_password" class="form-control" placeholder="Senha numérica ou alfanumérica" style="font-size:12px;padding:4px;">
                        </div>
                        <div style="margin-bottom:8px;">
                            <label style="font-size:11px;font-weight:600;display:block;margin-bottom:3px;">Senha Desenho (Padrão)</label>
                            <div class="pattern-lock-container">
                                <div class="pattern-grid-input" id="createPatternGrid">
                                    <?php for($i=1;$i<=9;$i++): ?>
                                    <div class="pattern-dot" data-dot="<?=$i?>" onclick="toggleDot(this,'create')"><span><?=$i?></span></div>
                                    <?php endfor; ?>
                                </div>
                                <input type="hidden" name="password_pattern" id="createPasswordPattern">
                                <button type="button" class="btn btn-secondary btn-sm" onclick="clearPattern('create')" style="margin-top:4px;font-size:10px;padding:3px 8px;"><i class="fas fa-redo"></i> Limpar</button>
                            </div>
                        </div>
                        <div>
                            <label style="font-size:11px;font-weight:600;display:block;margin-bottom:3px;"><i class="fas fa-camera"></i> Foto do Aparelho</label>
                            <input type="file" name="os_image" class="form-control" accept="image/*" style="font-size:11px;padding:3px;">
                            <small style="color:#888;font-size:10px;">Registro interno, não será impresso</small>
                        </div>
                    </div>
                </div>

            <script>
            const availableProducts = <?= json_encode($products) ?>;

            function searchProducts(mode) {
                const searchInput = document.getElementById(mode + '_product_search');
                const resultsDiv = document.getElementById(mode + '_product_results');
                const searchTerm = searchInput.value.toLowerCase().trim();
                if (searchTerm.length < 2) { resultsDiv.style.display = 'none'; return; }
                const filteredProducts = availableProducts.filter(p => (p.type || 'product') === 'product' && p.name.toLowerCase().includes(searchTerm));
                if (filteredProducts.length === 0) { resultsDiv.innerHTML = '<div style="padding:10px;color:#999;">Nenhum produto encontrado</div>'; resultsDiv.style.display = 'block'; return; }
                let html = '';
                filteredProducts.forEach(p => {
                    const outOfStock = parseInt(p.stock_quantity) <= 0;
                    if (outOfStock) {
                        html += `<div style="padding:10px;border-bottom:1px solid #f0f0f0;opacity:0.7;cursor:not-allowed;background:#fff5f5;">
                            <strong style="color:#999;">${p.name}</strong>
                            <span style="background:#ff4444;color:white;padding:2px 8px;border-radius:10px;font-size:0.7rem;margin-left:8px;">SEM ESTOQUE</span><br>
                            <small style="color:#999;">R$ ${parseFloat(p.price).toFixed(2).replace('.', ',')} - Estoque: 0</small>
                        </div>`;
                    } else {
                        html += `<div style="padding:10px;cursor:pointer;border-bottom:1px solid #f0f0f0;"
                                      onmouseover="this.style.background='#f8f9ff'" onmouseout="this.style.background='white'"
                                      onclick="selectProduct('${mode}', ${p.id}, '${p.name.replace(/'/g, "\\'")}', ${p.price}, ${p.stock_quantity}, ${p.allow_price_edit || 0})">
                            <strong>${p.name}</strong><br>
                            <small style="color:#666;">R$ ${parseFloat(p.price).toFixed(2).replace('.', ',')} - Estoque: ${p.stock_quantity}</small>
                        </div>`;
                    }
                });
                resultsDiv.innerHTML = html;
                resultsDiv.style.display = 'block';
            }

            function searchServices(mode) {
                const searchInput = document.getElementById(mode + '_service_search');
                const resultsDiv = document.getElementById(mode + '_service_results');
                const searchTerm = searchInput.value.toLowerCase().trim();
                if (searchTerm.length < 2) { resultsDiv.style.display = 'none'; return; }
                const filteredServices = availableProducts.filter(p => (p.type || 'product') === 'service' && p.name.toLowerCase().includes(searchTerm));
                if (filteredServices.length === 0) { resultsDiv.innerHTML = '<div style="padding:10px;color:#999;">Nenhum serviço encontrado</div>'; resultsDiv.style.display = 'block'; return; }
                let html = '';
                filteredServices.forEach(p => {
                    const priceVal = p.price ? parseFloat(p.price) : null;
                    const priceText = priceVal ? 'R$ ' + priceVal.toFixed(2).replace('.', ',') : 'Preço a definir';
                    html += `<div style="padding:10px;cursor:pointer;border-bottom:1px solid #f0f0f0;"
                                  onmouseover="this.style.background='#f0fff0'" onmouseout="this.style.background='white'"
                                  onclick="selectService('${mode}', ${p.id}, '${p.name.replace(/'/g, "\\'")}', ${priceVal || 'null'}, ${p.allow_price_edit || 0})">
                        <strong><i class="fas fa-tools" style="color:#4CAF50;"></i> ${p.name}</strong><br>
                        <small style="color:#666;">${priceText}</small>
                    </div>`;
                });
                resultsDiv.innerHTML = html;
                resultsDiv.style.display = 'block';
            }

            function selectProduct(mode, id, name, price, stock, allowPriceEdit) {
                document.getElementById(mode + '_product_search').value = name;
                document.getElementById(mode + '_selected_product_id').value = id;
                document.getElementById(mode + '_selected_product_name').value = name;
                document.getElementById(mode + '_product_price_input').value = parseFloat(price).toFixed(2);
                document.getElementById(mode + '_selected_product_stock').value = stock;
                document.getElementById(mode + '_product_results').style.display = 'none';
                const priceInput = document.getElementById(mode + '_product_price_input');
                if (parseInt(allowPriceEdit) === 1) { priceInput.readOnly = false; priceInput.style.background = ''; }
                else { priceInput.readOnly = true; priceInput.style.background = '#e9ecef'; }
            }

            function selectService(mode, id, name, price, allowPriceEdit) {
                document.getElementById(mode + '_service_search').value = name;
                document.getElementById(mode + '_selected_service_id').value = id;
                document.getElementById(mode + '_selected_service_name').value = name;
                document.getElementById(mode + '_service_price').value = price ? parseFloat(price).toFixed(2) : '';
                document.getElementById(mode + '_service_results').style.display = 'none';
                const priceInput = document.getElementById(mode + '_service_price');
                if (parseInt(allowPriceEdit) === 1) { priceInput.readOnly = false; priceInput.style.background = ''; }
                else { priceInput.readOnly = true; priceInput.style.background = '#e9ecef'; }
            }

            document.addEventListener('click', function(e) {
                ['create', 'edit'].forEach(function(mode) {
                    var prodResults = document.getElementById(mode + '_product_results');
                    var servResults = document.getElementById(mode + '_service_results');
                    if (!e.target.closest('#' + mode + '_product_search') && !e.target.closest('#' + mode + '_product_results')) { if (prodResults) prodResults.style.display = 'none'; }
                    if (!e.target.closest('#' + mode + '_service_search') && !e.target.closest('#' + mode + '_service_results')) { if (servResults) servResults.style.display = 'none'; }
                });
            });
            </script>

                <!-- TAB: Pagamento -->
                <div id="create_tab_payment" style="display:none;padding:10px;background:#fafafa;border:1px solid #e0e0e0;border-top:none;border-radius:0 0 8px 8px;">
                    <!-- Responsáveis -->
                    <div style="display:flex;gap:30px;margin-bottom:10px;">
                        <div style="flex:0 0 350px;">
                            <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;"><i class="fas fa-wrench"></i> Técnico Responsável</label>
                            <div style="position:relative;">
                                <input type="text" name="technician_name" id="createTechnicianSearch" class="form-control" placeholder="Buscar técnico..." autocomplete="off" style="font-size:12px;">
                                <div id="createTechnicianSuggestions" class="os-autocomplete-suggestions" style="display:none;"></div>
                            </div>
                        </div>
                        <div style="flex:0 0 350px;">
                            <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;"><i class="fas fa-user"></i> Atendente Responsável</label>
                            <div style="position:relative;">
                                <input type="text" name="attendant_name" id="createAttendantSearch" class="form-control" placeholder="Buscar atendente..." autocomplete="off" style="font-size:12px;">
                                <div id="createAttendantSuggestions" class="os-autocomplete-suggestions" style="display:none;"></div>
                            </div>
                        </div>
                    </div>
                    <!-- Resumo Financeiro -->
                    <div id="create_financial_summary" style="background:#f8f9ff;padding:12px;border-radius:8px;margin-bottom:10px;">
                        <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:6px 10px;padding-bottom:8px;margin-bottom:8px;border-bottom:1px solid #e0e0e0;">
                            <div style="text-align:center;">
                                <small style="color:#667eea;font-weight:bold;">Produtos</small>
                                <div id="create_total_products_display" style="font-size:15px;font-weight:bold;color:#667eea;">R$ 0,00</div>
                            </div>
                            <div style="text-align:center;">
                                <small style="color:#4CAF50;font-weight:bold;">Serviços</small>
                                <div id="create_total_services_display" style="font-size:15px;font-weight:bold;color:#4CAF50;">R$ 0,00</div>
                            </div>
                            <div style="text-align:center;">
                                <small style="color:#e74c3c;font-weight:bold;">Desconto</small>
                                <div id="create_discount_display" style="font-size:15px;font-weight:bold;color:#e74c3c;">- R$ 0,00</div>
                            </div>
                            <div style="text-align:center;">
                                <small style="color:#333;font-weight:bold;">Total Final</small>
                                <div style="display:flex;align-items:center;justify-content:center;gap:5px;">
                                    <span style="color:#333;font-weight:bold;">R$</span>
                                    <input type="number" step="0.01" name="total_cost" id="create_total_cost" class="form-control" style="width:100px;font-size:15px;font-weight:bold;text-align:center;color:#333;" value="0" oninput="updateFinancialBalance('create')">
                                </div>
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:6px 10px;">
                            <div style="text-align:center;">
                                <small style="color:#9C27B0;font-weight:bold;">Entrada/Sinal</small>
                                <div id="create_deposit_display" style="font-size:14px;font-weight:bold;color:#9C27B0;">R$ 0,00</div>
                            </div>
                            <div style="text-align:center;">
                                <small style="color:#2196F3;font-weight:bold;">Pagamentos</small>
                                <div id="create_payments_total_display" style="font-size:14px;font-weight:bold;color:#2196F3;">R$ 0,00</div>
                            </div>
                            <div style="text-align:center;">
                                <small style="color:#f44336;font-weight:bold;">Restante</small>
                                <div id="create_remaining_display" style="font-size:14px;font-weight:bold;color:#f44336;">R$ 0,00</div>
                            </div>
                            <div></div>
                        </div>
                    </div>
                    <!-- Desconto + Entrada/Sinal side by side -->
                    <div style="display:flex;gap:16px;align-items:flex-start;margin-bottom:10px;">
                        <div style="background:#ffeef0;padding:12px;border-radius:8px;">
                            <strong style="color:#e74c3c;font-size:12px;"><i class="fas fa-percentage"></i> Desconto</strong>
                            <div style="display:flex;gap:16px;margin-top:6px;">
                                <div>
                                    <label style="font-size:11px;font-weight:600;display:block;margin-bottom:3px;">Valor (R$)</label>
                                    <input type="number" step="0.01" name="discount" id="create_discount" class="form-control" placeholder="0,00" value="0" min="0" oninput="applyDiscount('create')" style="font-size:12px;width:180px;">
                                </div>
                                <div>
                                    <label style="font-size:11px;font-weight:600;display:block;margin-bottom:3px;">ou Percentual (%)</label>
                                    <input type="number" step="0.1" id="create_discount_percent" class="form-control" placeholder="0%" min="0" max="100" oninput="applyDiscountPercent('create')" style="font-size:12px;width:150px;">
                                </div>
                            </div>
                        </div>
                        <div style="flex:0 0 220px;">
                            <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;"><i class="fas fa-hand-holding-usd"></i> Entrada/Sinal (R$)</label>
                            <input type="number" step="0.01" name="deposit_amount" id="create_deposit_input" class="form-control" placeholder="0,00" value="0" oninput="updateFinancialBalance('create')" style="font-size:13px;">
                            <small style="color:#888;font-size:11px;">Valor pago antecipadamente pelo cliente</small>
                        </div>
                    </div>
                    <!-- Formas de Pagamento -->
                    <div style="background:#fff3e0;padding:12px;border-radius:8px;margin-bottom:10px;">
                        <strong style="color:#FF9800;font-size:12px;"><i class="fas fa-credit-card"></i> Adicionar Forma de Pagamento</strong>
                        <div style="display:flex;gap:16px;margin-top:8px;align-items:flex-end;">
                            <div>
                                <label style="font-size:11px;font-weight:600;display:block;margin-bottom:3px;">Forma</label>
                                <select id="create_new_payment_method" class="form-control" onchange="togglePaymentExtras('create')" style="font-size:12px;width:260px;">
                                    <option value="dinheiro">Dinheiro</option>
                                    <option value="pix">PIX</option>
                                    <option value="cartao_credito">Cartão Crédito</option>
                                    <option value="cartao_debito">Cartão Débito</option>
                                </select>
                            </div>
                            <div>
                                <label style="font-size:11px;font-weight:600;display:block;margin-bottom:3px;">Valor (R$)</label>
                                <input type="number" step="0.01" id="create_new_payment_amount" class="form-control" placeholder="0,00" oninput="calculateChange('create')" style="font-size:12px;width:140px;">
                            </div>
                            <div id="create_installments_group" style="display:none;">
                                <label style="font-size:11px;font-weight:600;display:block;margin-bottom:3px;">Parcelas</label>
                                <select id="create_new_payment_installments" class="form-control" style="font-size:12px;width:90px;">
                                    <?php for($i=1;$i<=12;$i++): ?><option value="<?=$i?>"><?=$i?>x</option><?php endfor; ?>
                                </select>
                            </div>
                            <div id="create_change_group" style="display:none;">
                                <label style="font-size:11px;font-weight:600;display:block;margin-bottom:3px;">Troco</label>
                                <input type="text" id="create_change_display" class="form-control" readonly style="background:#f0f0f0;font-weight:bold;color:#4CAF50;font-size:12px;width:110px;">
                            </div>
                            <div>
                                <label style="font-size:11px;font-weight:600;display:block;margin-bottom:3px;">&nbsp;</label>
                                <button type="button" class="btn btn-primary" onclick="addPaymentToOS('create')" style="background:linear-gradient(45deg,#FF9800,#F57C00);white-space:nowrap;font-size:12px;padding:6px 16px;">
                                    <i class="fas fa-plus"></i> Adicionar
                                </button>
                            </div>
                        </div>
                    </div>
                    <div id="create_payments_list"></div>
                    <input type="hidden" name="payment_methods" id="create_payment_methods_data" value="[]">
                    <!-- Garantia -->
                    <div style="display:flex;gap:16px;align-items:flex-end;margin-top:10px;">
                        <div style="flex:0 0 300px;">
                            <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;"><i class="fas fa-shield-alt"></i> Período de Garantia</label>
                            <select name="warranty_period" class="form-control" style="font-size:12px;">
                                <option value="30 dias">30 dias</option>
                                <option value="60 dias">60 dias</option>
                                <option value="90 dias" selected>90 dias (Padrão)</option>
                                <option value="3 meses">3 meses</option>
                                <option value="4 meses">4 meses</option>
                                <option value="6 meses">6 meses</option>
                                <option value="1 ano">1 ano</option>
                                <option value="Sem garantia">Sem garantia</option>
                            </select>
                        </div>
                        <div style="flex:0 0 auto;">
                            <button type="button" class="btn btn-secondary" onclick="window.open('../settings/warranty_config.php', '_blank')" style="white-space:nowrap;font-size:12px;">
                                <i class="fas fa-cog"></i> Configurar Termos
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FOOTER: financial totals -->
            <div style="display:flex;gap:0;margin-top:10px;border-radius:8px;overflow:hidden;border:1px solid #e0e0e0;">
                <div style="flex:1;text-align:center;padding:8px;background:#f8f9ff;border-right:1px solid #e0e0e0;">
                    <div style="font-size:10px;font-weight:700;color:#667eea;text-transform:uppercase;">Total Bruto</div>
                    <div id="create_footer_bruto" style="font-size:14px;font-weight:bold;color:#667eea;">R$ 0,00</div>
                </div>
                <div style="flex:1;text-align:center;padding:8px;background:#fff5f5;border-right:1px solid #e0e0e0;">
                    <div style="font-size:10px;font-weight:700;color:#e74c3c;text-transform:uppercase;">Desconto</div>
                    <div id="create_footer_desconto" style="font-size:14px;font-weight:bold;color:#e74c3c;">R$ 0,00</div>
                </div>
                <div style="flex:1;text-align:center;padding:8px;background:#f0fff0;border-right:1px solid #e0e0e0;">
                    <div style="font-size:10px;font-weight:700;color:#4CAF50;text-transform:uppercase;">Total Líquido</div>
                    <div id="create_footer_liquido" style="font-size:14px;font-weight:bold;color:#4CAF50;">R$ 0,00</div>
                </div>
                <div style="flex:1;text-align:center;padding:8px;background:#f8f9fa;border-right:1px solid #e0e0e0;">
                    <div style="font-size:10px;font-weight:700;color:#333;text-transform:uppercase;">Total Itens</div>
                    <div id="create_footer_itens" style="font-size:14px;font-weight:bold;color:#333;">0</div>
                </div>
                <div style="flex:1;text-align:center;padding:8px;background:#e8f5e9;border-right:1px solid #e0e0e0;">
                    <div style="font-size:10px;font-weight:700;color:#2E7D32;text-transform:uppercase;">Total Pago</div>
                    <div id="create_footer_pago" style="font-size:14px;font-weight:bold;color:#2E7D32;">R$ 0,00</div>
                </div>
                <div style="flex:1;text-align:center;padding:8px;background:#fff8e1;">
                    <div style="font-size:10px;font-weight:700;color:#FF9800;text-transform:uppercase;">Troco</div>
                    <div id="create_footer_troco" style="font-size:14px;font-weight:bold;color:#FF9800;">R$ 0,00</div>
                </div>
            </div>

            <div style="text-align:right;margin-top:12px;display:flex;gap:10px;justify-content:flex-end;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar</button>
                <button type="button" class="btn btn-danger" onclick="confirmCloseOS('createModal')"><i class="fas fa-times"></i> Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDITAR OS -->
<div id="editModal" class="modal">
    <div class="modal-content" style="max-width:95vw;width:1300px;">
        <div class="modal-header">
            <h2><i class="fas fa-edit"></i> Editar Ordem de Serviço</h2>
            <button class="close" onclick="confirmCloseOS('editModal')">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">

            <!-- ROW 1: Cliente + Tipo + Modelo + Status -->
            <div style="display:flex;gap:30px;margin-bottom:8px;align-items:flex-end;">
                <div style="flex:0 0 320px;">
                    <label style="font-size:12px;font-weight:600;margin-bottom:3px;display:block;">Cliente * <a href="#" onclick="openQuickCustomerModal(event)" style="font-size:11px;color:#00b894;margin-left:6px;"><i class="fas fa-plus-circle"></i> Cadastrar novo</a></label>
                    <div style="position:relative;">
                        <input type="text" id="editCustomerSearch" class="form-control" placeholder="Buscar por nome, CPF ou telefone..." autocomplete="off" style="font-size:13px;">
                        <input type="hidden" name="customer_id" id="edit_customer_id" required>
                        <div id="editCustomerSuggestions" class="os-autocomplete-suggestions" style="display:none;"></div>
                    </div>
                </div>
                <div style="flex:0 0 160px;">
                    <label style="font-size:12px;font-weight:600;margin-bottom:3px;display:block;">Tipo de Produto</label>
                    <select name="device_type" id="edit_device_type" class="form-control" style="font-size:13px;">
                        <option value="celular">Celular</option>
                        <option value="tablet">Tablet</option>
                        <option value="notebook">Notebook</option>
                        <option value="smartwatch">Smartwatch</option>
                        <option value="console">Console</option>
                        <option value="outro">Outro</option>
                    </select>
                </div>
                <div style="flex:0 0 280px;">
                    <label style="font-size:12px;font-weight:600;margin-bottom:3px;display:block;">Modelo do Aparelho *</label>
                    <input type="text" name="device" id="edit_device" class="form-control" required style="font-size:13px;">
                </div>
                <div style="flex:0 0 160px;">
                    <label style="font-size:12px;font-weight:600;margin-bottom:3px;display:block;">Status *</label>
                    <select name="status" id="edit_status" class="form-control" required style="font-size:13px;">
                        <option value="open">Aguardando</option>
                        <option value="in_progress">Em Andamento</option>
                        <option value="completed">Concluído</option>
                        <option value="invoiced">Faturada</option>
                        <option value="delivered">Entregue</option>
                        <option value="cancelled">Cancelado</option>
                    </select>
                </div>
            </div>

            <!-- ROW 2: 4 textareas side by side -->
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:30px;margin-bottom:10px;">
                <div>
                    <label style="font-size:12px;font-weight:600;margin-bottom:3px;display:block;">Problema Relatado</label>
                    <textarea name="reported_problem" id="edit_reported_problem" class="form-control" rows="3" style="font-size:12px;resize:vertical;"></textarea>
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;margin-bottom:3px;display:block;">Laudo Técnico</label>
                    <textarea name="technical_report" id="edit_technical_report" class="form-control" rows="3" style="font-size:12px;resize:vertical;"></textarea>
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;margin-bottom:3px;display:block;">Observações para o Cliente</label>
                    <textarea name="customer_observations" id="edit_customer_observations" class="form-control" rows="3" style="font-size:12px;resize:vertical;"></textarea>
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;margin-bottom:3px;display:block;">Observações Internas</label>
                    <textarea name="internal_observations" id="edit_internal_observations" class="form-control" rows="3" style="font-size:12px;resize:vertical;background:#fff9e6;"></textarea>
                </div>
            </div>
            <!-- TABS -->
            <div style="margin-bottom:0;">
                <div style="display:flex;gap:0;border-bottom:2px solid #667eea;">
                    <button type="button" id="edit_tab_products_btn" onclick="switchTab('edit','products')" style="padding:7px 18px;border:none;background:linear-gradient(45deg,#667eea,#764ba2);color:white;font-weight:600;font-size:13px;border-radius:6px 6px 0 0;cursor:pointer;">
                        <i class="fas fa-box"></i> Produtos e Serviços
                    </button>
                    <button type="button" id="edit_tab_payment_btn" onclick="switchTab('edit','payment')" style="padding:7px 18px;border:none;background:#e9ecef;color:#666;font-weight:600;font-size:13px;border-radius:6px 6px 0 0;cursor:pointer;margin-left:4px;">
                        <i class="fas fa-dollar-sign"></i> Pagamento
                    </button>
                </div>

                <!-- TAB: Produtos e Serviços -->
                <div id="edit_tab_products" style="display:flex;gap:10px;padding:10px;background:#fafafa;border:1px solid #e0e0e0;border-top:none;border-radius:0 0 8px 8px;">
                    <!-- LEFT: products/services -->
                    <div style="flex:1;min-width:0;">
                        <!-- Adicionar Produto -->
                        <div style="background:#f8f9ff;padding:10px;border-radius:8px;margin-bottom:8px;">
                            <strong style="color:#667eea;font-size:12px;"><i class="fas fa-box"></i> Adicionar Produto</strong>
                            <div style="display:grid;grid-template-columns:6fr 1.5fr 1fr 1.5fr;gap:30px;margin-top:8px;align-items:end;">
                                <div style="position:relative;">
                                    <label style="font-size:11px;font-weight:600;display:block;margin-bottom:4px;">Buscar Produto</label>
                                    <input type="text" id="edit_product_search" class="form-control" placeholder="Digite o nome do produto..." autocomplete="off" oninput="searchProducts('edit')" style="font-size:12px;">
                                    <div id="edit_product_results" style="display:none;position:absolute;top:100%;left:0;right:0;background:white;border:1px solid #ddd;border-radius:8px;max-height:200px;overflow-y:auto;z-index:1000;box-shadow:0 4px 6px rgba(0,0,0,0.1);"></div>
                                    <input type="hidden" id="edit_selected_product_id">
                                    <input type="hidden" id="edit_selected_product_name">
                                    <input type="hidden" id="edit_selected_product_stock">
                                </div>
                                <div>
                                    <label style="font-size:11px;font-weight:600;display:block;margin-bottom:4px;">Preço Unit. (R$)</label>
                                    <input type="number" step="0.01" id="edit_product_price_input" class="form-control" placeholder="0,00" style="font-size:12px;">
                                </div>
                                <div>
                                    <label style="font-size:11px;font-weight:600;display:block;margin-bottom:4px;">Qtd</label>
                                    <input type="number" id="edit_product_quantity" class="form-control" value="1" min="1" style="font-size:12px;">
                                </div>
                                <div style="display:flex;flex-direction:column;">
                                    <label style="font-size:11px;font-weight:600;display:block;margin-bottom:4px;visibility:hidden;">.</label>
                                    <button type="button" class="btn btn-primary" onclick="addProductToOS('edit')" style="width:100%;font-size:12px;flex:1;">
                                        <i class="fas fa-plus"></i> Adicionar
                                    </button>
                                </div>
                            </div>
                        </div>
                        <!-- Adicionar Serviço -->
                        <div style="background:#f0fff0;padding:10px;border-radius:8px;margin-bottom:8px;">
                            <strong style="color:#4CAF50;font-size:12px;"><i class="fas fa-tools"></i> Adicionar Serviço</strong>
                            <div style="display:grid;grid-template-columns:6fr 1.5fr 1fr 1.5fr;gap:30px;margin-top:8px;align-items:end;">
                                <div style="position:relative;">
                                    <label style="font-size:11px;font-weight:600;display:block;margin-bottom:4px;">Buscar Serviço</label>
                                    <input type="text" id="edit_service_search" class="form-control" placeholder="Digite o nome do serviço..." autocomplete="off" oninput="searchServices('edit')" style="font-size:12px;">
                                    <div id="edit_service_results" style="display:none;position:absolute;top:100%;left:0;right:0;background:white;border:1px solid #ddd;border-radius:8px;max-height:200px;overflow-y:auto;z-index:1000;box-shadow:0 4px 6px rgba(0,0,0,0.1);"></div>
                                    <input type="hidden" id="edit_selected_service_id">
                                    <input type="hidden" id="edit_selected_service_name">
                                </div>
                                <div>
                                    <label style="font-size:11px;font-weight:600;display:block;margin-bottom:4px;">Preço (R$)</label>
                                    <input type="number" step="0.01" id="edit_service_price" class="form-control" placeholder="0,00" style="font-size:12px;">
                                </div>
                                <div>
                                    <label style="font-size:11px;font-weight:600;display:block;margin-bottom:4px;">Qtd</label>
                                    <input type="number" id="edit_service_quantity" class="form-control" value="1" min="1" style="font-size:12px;">
                                </div>
                                <div style="display:flex;flex-direction:column;">
                                    <label style="font-size:11px;font-weight:600;display:block;margin-bottom:4px;visibility:hidden;">.</label>
                                    <button type="button" class="btn btn-primary" onclick="addServiceToOS('edit')" style="width:100%;font-size:12px;flex:1;background:linear-gradient(45deg,#4CAF50,#45a049);">
                                        <i class="fas fa-plus"></i> Adicionar
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div id="edit_products_list"></div>
                        <input type="hidden" id="edit_products_data" name="products_data" value="[]">
                    </div>
                    <!-- RIGHT SIDEBAR: Checklist + Device info -->
                    <div style="flex:0 0 240px;background:white;border:1px solid #e0e0e0;border-radius:8px;padding:10px;">
                        <div style="font-size:12px;font-weight:700;color:#667eea;margin-bottom:8px;border-bottom:1px solid #f0f0f0;padding-bottom:5px;"><i class="fas fa-clipboard-check"></i> Checklist</div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:5px;margin-bottom:10px;">
                            <label style="display:flex;align-items:center;gap:5px;font-size:12px;cursor:pointer;"><input type="checkbox" name="checklist_lens" id="edit_checklist_lens" value="1"> Lente</label>
                            <label style="display:flex;align-items:center;gap:5px;font-size:12px;cursor:pointer;"><input type="checkbox" name="checklist_screen" id="edit_checklist_screen" value="1"> Tela Trincada</label>
                            <label style="display:flex;align-items:center;gap:5px;font-size:12px;cursor:pointer;"><input type="checkbox" name="checklist_sim_card" id="edit_checklist_sim_card" value="1"> Chip</label>
                            <label style="display:flex;align-items:center;gap:5px;font-size:12px;cursor:pointer;"><input type="checkbox" name="checklist_back_cover" id="edit_checklist_back_cover" value="1"> Tampa Traseira</label>
                            <label style="display:flex;align-items:center;gap:5px;font-size:12px;cursor:pointer;"><input type="checkbox" name="checklist_face_id" id="edit_checklist_face_id" value="1"> Sem Face ID</label>
                            <label style="display:flex;align-items:center;gap:5px;font-size:12px;cursor:pointer;"><input type="checkbox" name="checklist_connector" id="edit_checklist_connector" value="1"> Conector</label>
                            <label style="display:flex;align-items:center;gap:5px;font-size:12px;cursor:pointer;"><input type="checkbox" name="checklist_camera_front_back" id="edit_checklist_camera_front_back" value="1"> Câmera</label>
                        </div>
                        <div style="font-size:12px;font-weight:700;color:#667eea;margin-bottom:6px;border-bottom:1px solid #f0f0f0;padding-bottom:5px;"><i class="fas fa-mobile-alt"></i> Aparelho</div>
                        <div style="margin-bottom:8px;">
                            <label style="font-size:11px;font-weight:600;display:block;margin-bottom:3px;">O Aparelho Liga?</label>
                            <select name="device_powers_on" id="edit_device_powers_on" class="form-control" style="font-size:12px;padding:4px;">
                                <option value="sim">Sim</option>
                                <option value="nao">Não</option>
                            </select>
                        </div>
                        <div style="margin-bottom:8px;">
                            <label style="font-size:11px;font-weight:600;display:block;margin-bottom:3px;">Senha do Aparelho</label>
                            <input type="text" name="device_password" id="edit_device_password" class="form-control" placeholder="Senha numérica ou alfanumérica" style="font-size:12px;padding:4px;">
                        </div>
                        <div style="margin-bottom:8px;">
                            <label style="font-size:11px;font-weight:600;display:block;margin-bottom:3px;">Senha Desenho (Padrão)</label>
                            <div class="pattern-lock-container">
                                <div class="pattern-grid-input" id="editPatternGrid">
                                    <?php for($i=1;$i<=9;$i++): ?>
                                    <div class="pattern-dot" data-dot="<?=$i?>" onclick="toggleDot(this,'edit')"><span><?=$i?></span></div>
                                    <?php endfor; ?>
                                </div>
                                <input type="hidden" name="password_pattern" id="editPasswordPattern">
                                <button type="button" class="btn btn-secondary btn-sm" onclick="clearPattern('edit')" style="margin-top:4px;font-size:10px;padding:3px 8px;"><i class="fas fa-redo"></i> Limpar</button>
                            </div>
                        </div>
                        <div>
                            <label style="font-size:11px;font-weight:600;display:block;margin-bottom:3px;"><i class="fas fa-camera"></i> Foto do Aparelho</label>
                            <div id="edit_os_image_preview" style="display:none;margin-bottom:6px;">
                                <img id="edit_os_image_thumb" src="" style="max-width:100%;max-height:80px;border-radius:6px;border:2px solid #ddd;">
                                <button type="button" class="btn btn-danger btn-sm" onclick="removeOsImage('edit')" style="margin-top:4px;font-size:10px;padding:3px 8px;"><i class="fas fa-trash"></i> Remover</button>
                                <input type="hidden" name="remove_os_image" id="edit_remove_os_image" value="">
                            </div>
                            <input type="file" name="os_image" class="form-control" accept="image/*" style="font-size:11px;padding:3px;">
                            <small style="color:#888;font-size:10px;">Registro interno, não será impresso</small>
                        </div>
                    </div>
                </div>

                <!-- TAB: Pagamento -->
                <div id="edit_tab_payment" style="display:none;padding:10px;background:#fafafa;border:1px solid #e0e0e0;border-top:none;border-radius:0 0 8px 8px;">
                    <!-- Responsáveis -->
                    <div style="display:flex;gap:30px;margin-bottom:10px;">
                        <div style="flex:0 0 350px;">
                            <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;"><i class="fas fa-wrench"></i> Técnico Responsável</label>
                            <div style="position:relative;">
                                <input type="text" name="technician_name" id="edit_technician_name" class="form-control" placeholder="Buscar técnico..." autocomplete="off" style="font-size:12px;">
                                <div id="editTechnicianSuggestions" class="os-autocomplete-suggestions" style="display:none;"></div>
                            </div>
                        </div>
                        <div style="flex:0 0 350px;">
                            <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;"><i class="fas fa-user"></i> Atendente Responsável</label>
                            <div style="position:relative;">
                                <input type="text" name="attendant_name" id="edit_attendant_name" class="form-control" placeholder="Buscar atendente..." autocomplete="off" style="font-size:12px;">
                                <div id="editAttendantSuggestions" class="os-autocomplete-suggestions" style="display:none;"></div>
                            </div>
                        </div>
                    </div>
                    <!-- Resumo Financeiro -->
                    <div id="edit_financial_summary" style="background:#f8f9ff;padding:12px;border-radius:8px;margin-bottom:10px;">
                        <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:6px 10px;padding-bottom:8px;margin-bottom:8px;border-bottom:1px solid #e0e0e0;">
                            <div style="text-align:center;">
                                <small style="color:#667eea;font-weight:bold;">Produtos</small>
                                <div id="edit_total_products_display" style="font-size:15px;font-weight:bold;color:#667eea;">R$ 0,00</div>
                            </div>
                            <div style="text-align:center;">
                                <small style="color:#4CAF50;font-weight:bold;">Serviços</small>
                                <div id="edit_total_services_display" style="font-size:15px;font-weight:bold;color:#4CAF50;">R$ 0,00</div>
                            </div>
                            <div style="text-align:center;">
                                <small style="color:#e74c3c;font-weight:bold;">Desconto</small>
                                <div id="edit_discount_display" style="font-size:15px;font-weight:bold;color:#e74c3c;">- R$ 0,00</div>
                            </div>
                            <div style="text-align:center;">
                                <small style="color:#333;font-weight:bold;">Total Final</small>
                                <div style="display:flex;align-items:center;justify-content:center;gap:5px;">
                                    <span style="color:#333;font-weight:bold;">R$</span>
                                    <input type="number" step="0.01" name="total_cost" id="edit_total_cost" class="form-control" style="width:100px;font-size:15px;font-weight:bold;text-align:center;color:#333;" value="0" oninput="updateFinancialBalance('edit')">
                                </div>
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:6px 10px;">
                            <div style="text-align:center;">
                                <small style="color:#9C27B0;font-weight:bold;">Entrada/Sinal</small>
                                <div id="edit_deposit_display" style="font-size:14px;font-weight:bold;color:#9C27B0;">R$ 0,00</div>
                            </div>
                            <div style="text-align:center;">
                                <small style="color:#2196F3;font-weight:bold;">Pagamentos</small>
                                <div id="edit_payments_total_display" style="font-size:14px;font-weight:bold;color:#2196F3;">R$ 0,00</div>
                            </div>
                            <div style="text-align:center;">
                                <small style="color:#f44336;font-weight:bold;">Restante</small>
                                <div id="edit_remaining_display" style="font-size:14px;font-weight:bold;color:#f44336;">R$ 0,00</div>
                            </div>
                            <div></div>
                        </div>
                    </div>
                    <!-- Desconto + Entrada/Sinal side by side -->
                    <div style="display:flex;gap:16px;align-items:flex-start;margin-bottom:10px;">
                        <div style="background:#ffeef0;padding:12px;border-radius:8px;">
                            <strong style="color:#e74c3c;font-size:12px;"><i class="fas fa-percentage"></i> Desconto</strong>
                            <div style="display:flex;gap:16px;margin-top:6px;">
                                <div>
                                    <label style="font-size:11px;font-weight:600;display:block;margin-bottom:3px;">Valor (R$)</label>
                                    <input type="number" step="0.01" name="discount" id="edit_discount" class="form-control" placeholder="0,00" value="0" min="0" oninput="applyDiscount('edit')" style="font-size:12px;width:180px;">
                                </div>
                                <div>
                                    <label style="font-size:11px;font-weight:600;display:block;margin-bottom:3px;">ou Percentual (%)</label>
                                    <input type="number" step="0.1" id="edit_discount_percent" class="form-control" placeholder="0%" min="0" max="100" oninput="applyDiscountPercent('edit')" style="font-size:12px;width:150px;">
                                </div>
                            </div>
                        </div>
                        <div style="flex:0 0 220px;">
                            <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;"><i class="fas fa-hand-holding-usd"></i> Entrada/Sinal (R$)</label>
                            <input type="number" step="0.01" name="deposit_amount" id="edit_deposit_amount" class="form-control" placeholder="0,00" value="0" oninput="updateFinancialBalance('edit')" style="font-size:13px;">
                            <small style="color:#888;font-size:11px;">Valor pago antecipadamente pelo cliente</small>
                        </div>
                    </div>
                    <!-- Formas de Pagamento -->
                    <div style="background:#fff3e0;padding:12px;border-radius:8px;margin-bottom:10px;">
                        <strong style="color:#FF9800;font-size:12px;"><i class="fas fa-credit-card"></i> Adicionar Forma de Pagamento</strong>
                        <div style="display:flex;gap:16px;margin-top:8px;align-items:flex-end;">
                            <div>
                                <label style="font-size:11px;font-weight:600;display:block;margin-bottom:3px;">Forma</label>
                                <select id="edit_new_payment_method" class="form-control" onchange="togglePaymentExtras('edit')" style="font-size:12px;width:260px;">
                                    <option value="dinheiro">Dinheiro</option>
                                    <option value="pix">PIX</option>
                                    <option value="cartao_credito">Cartão Crédito</option>
                                    <option value="cartao_debito">Cartão Débito</option>
                                </select>
                            </div>
                            <div>
                                <label style="font-size:11px;font-weight:600;display:block;margin-bottom:3px;">Valor (R$)</label>
                                <input type="number" step="0.01" id="edit_new_payment_amount" class="form-control" placeholder="0,00" oninput="calculateChange('edit')" style="font-size:12px;width:140px;">
                            </div>
                            <div id="edit_installments_group" style="display:none;">
                                <label style="font-size:11px;font-weight:600;display:block;margin-bottom:3px;">Parcelas</label>
                                <select id="edit_new_payment_installments" class="form-control" style="font-size:12px;width:90px;">
                                    <?php for($i=1;$i<=12;$i++): ?><option value="<?=$i?>"><?=$i?>x</option><?php endfor; ?>
                                </select>
                            </div>
                            <div id="edit_change_group" style="display:none;">
                                <label style="font-size:11px;font-weight:600;display:block;margin-bottom:3px;">Troco</label>
                                <input type="text" id="edit_change_display" class="form-control" readonly style="background:#f0f0f0;font-weight:bold;color:#4CAF50;font-size:12px;width:110px;">
                            </div>
                            <div>
                                <label style="font-size:11px;font-weight:600;display:block;margin-bottom:3px;">&nbsp;</label>
                                <button type="button" class="btn btn-primary" onclick="addPaymentToOS('edit')" style="background:linear-gradient(45deg,#FF9800,#F57C00);white-space:nowrap;font-size:12px;padding:6px 16px;">
                                    <i class="fas fa-plus"></i> Adicionar
                                </button>
                            </div>
                        </div>
                    </div>
                    <div id="edit_payments_list"></div>
                    <input type="hidden" name="payment_methods" id="edit_payment_methods_data" value="[]">
                    <!-- Garantia -->
                    <div style="display:flex;gap:16px;align-items:flex-end;margin-top:10px;">
                        <div style="flex:0 0 300px;">
                            <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;"><i class="fas fa-shield-alt"></i> Período de Garantia</label>
                            <select name="warranty_period" id="edit_warranty_period" class="form-control" style="font-size:12px;">
                                <option value="30 dias">30 dias</option>
                                <option value="60 dias">60 dias</option>
                                <option value="90 dias">90 dias (Padrão)</option>
                                <option value="3 meses">3 meses</option>
                                <option value="4 meses">4 meses</option>
                                <option value="6 meses">6 meses</option>
                                <option value="1 ano">1 ano</option>
                                <option value="Sem garantia">Sem garantia</option>
                            </select>
                        </div>
                        <div style="flex:0 0 auto;">
                            <button type="button" class="btn btn-secondary" onclick="window.open('../settings/warranty_config.php', '_blank')" style="white-space:nowrap;font-size:12px;">
                                <i class="fas fa-cog"></i> Configurar Termos
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FOOTER: financial totals -->
            <div style="display:flex;gap:0;margin-top:10px;border-radius:8px;overflow:hidden;border:1px solid #e0e0e0;">
                <div style="flex:1;text-align:center;padding:8px;background:#f8f9ff;border-right:1px solid #e0e0e0;">
                    <div style="font-size:10px;font-weight:700;color:#667eea;text-transform:uppercase;">Total Bruto</div>
                    <div id="edit_footer_bruto" style="font-size:14px;font-weight:bold;color:#667eea;">R$ 0,00</div>
                </div>
                <div style="flex:1;text-align:center;padding:8px;background:#fff5f5;border-right:1px solid #e0e0e0;">
                    <div style="font-size:10px;font-weight:700;color:#e74c3c;text-transform:uppercase;">Desconto</div>
                    <div id="edit_footer_desconto" style="font-size:14px;font-weight:bold;color:#e74c3c;">R$ 0,00</div>
                </div>
                <div style="flex:1;text-align:center;padding:8px;background:#f0fff0;border-right:1px solid #e0e0e0;">
                    <div style="font-size:10px;font-weight:700;color:#4CAF50;text-transform:uppercase;">Total Líquido</div>
                    <div id="edit_footer_liquido" style="font-size:14px;font-weight:bold;color:#4CAF50;">R$ 0,00</div>
                </div>
                <div style="flex:1;text-align:center;padding:8px;background:#f8f9fa;border-right:1px solid #e0e0e0;">
                    <div style="font-size:10px;font-weight:700;color:#333;text-transform:uppercase;">Total Itens</div>
                    <div id="edit_footer_itens" style="font-size:14px;font-weight:bold;color:#333;">0</div>
                </div>
                <div style="flex:1;text-align:center;padding:8px;background:#e8f5e9;border-right:1px solid #e0e0e0;">
                    <div style="font-size:10px;font-weight:700;color:#2E7D32;text-transform:uppercase;">Total Pago</div>
                    <div id="edit_footer_pago" style="font-size:14px;font-weight:bold;color:#2E7D32;">R$ 0,00</div>
                </div>
                <div style="flex:1;text-align:center;padding:8px;background:#fff8e1;">
                    <div style="font-size:10px;font-weight:700;color:#FF9800;text-transform:uppercase;">Troco</div>
                    <div id="edit_footer_troco" style="font-size:14px;font-weight:bold;color:#FF9800;">R$ 0,00</div>
                </div>
            </div>

            <div style="text-align:right;margin-top:12px;display:flex;gap:10px;justify-content:flex-end;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Atualizar</button>
                <button type="button" class="btn btn-danger" onclick="confirmCloseOS('editModal')"><i class="fas fa-times"></i> Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Desbloquear OS -->
<div id="unlockModal" class="modal">
    <div class="modal-content" style="max-width: 400px;">
        <h2 style="color:#dc3545;margin-bottom:20px;">
            <i class="fas fa-unlock"></i> Desbloquear OS
        </h2>
        <p style="color:#666;margin-bottom:20px;">
            <i class="fas fa-exclamation-triangle"></i> Apenas administradores podem desbloquear ordens de serviço entregues.
        </p>
        <form id="unlockForm" onsubmit="unlockServiceOrder(event)">
            <input type="hidden" id="unlock_os_id" value="">

            <div class="form-group">
                <label>Email do Admin</label>
                <input type="email" id="unlock_username" class="form-control" required placeholder="admin@flreparos.com" autocomplete="off">
            </div>

            <div class="form-group">
                <label>Senha</label>
                <input type="password" id="unlock_password" class="form-control" required placeholder="Digite a senha" autocomplete="off">
            </div>

            <div style="text-align:right;margin-top:20px;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-unlock"></i> Desbloquear
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('unlockModal')">
                    <i class="fas fa-times"></i> Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

<script src="../../assets/js/main.js"></script>
<script>
// Dados para autocomplete
const osCustomers = <?php echo json_encode($customers); ?>;
const osTechnicians = <?php echo json_encode($technicians); ?>;
const osAttendants = <?php echo json_encode($attendants); ?>;

function openModal(id){document.getElementById(id).style.display='block';}
function closeModal(id){document.getElementById(id).style.display='none';}

// ===== AUTOCOMPLETE GENÉRICO =====
function setupOsAutocomplete(inputId, suggestionsId, dataList, options = {}) {
    const input = document.getElementById(inputId);
    const suggestions = document.getElementById(suggestionsId);
    if (!input || !suggestions) return;

    const { onSelect, showDetails, hiddenInputId } = options;

    function render(query) {
        let filtered;
        if (!query || query.length === 0) {
            filtered = dataList;
        } else {
            const q = query.toLowerCase();
            filtered = dataList.filter(item => {
                let match = item.name.toLowerCase().includes(q);
                if (item.cpf_cnpj) match = match || item.cpf_cnpj.includes(q);
                if (item.phone) match = match || item.phone.includes(q);
                return match;
            });
        }

        if (filtered.length > 0) {
            const html = filtered.map(item => {
                const escapedName = item.name.replace(/'/g, "\\'");
                let detailsHtml = '';
                if (showDetails && (item.cpf_cnpj || item.phone)) {
                    detailsHtml = `<div class="details">
                        ${item.cpf_cnpj ? `<span><i class="fas fa-id-card"></i> ${item.cpf_cnpj}</span>` : ''}
                        ${item.phone ? `<span><i class="fas fa-phone"></i> ${item.phone}</span>` : ''}
                    </div>`;
                }
                return `<div class="os-suggestion" data-id="${item.id}" data-name="${escapedName}">
                    <strong>${item.name}</strong>${detailsHtml}
                </div>`;
            }).join('');
            suggestions.innerHTML = html;
            suggestions.style.display = 'block';

            // Adicionar click handlers
            suggestions.querySelectorAll('.os-suggestion').forEach(el => {
                el.addEventListener('click', function() {
                    const id = this.dataset.id;
                    const name = this.dataset.name;
                    input.value = name;
                    if (hiddenInputId) {
                        document.getElementById(hiddenInputId).value = id;
                    }
                    suggestions.style.display = 'none';
                    if (onSelect) onSelect(id, name);
                });
            });
        } else {
            suggestions.innerHTML = '<div class="os-no-results">Nenhum resultado encontrado</div>';
            suggestions.style.display = 'block';
        }
    }

    input.addEventListener('focus', () => render(input.value.trim()));
    input.addEventListener('input', () => render(input.value.trim()));
    document.addEventListener('click', (e) => {
        if (!input.contains(e.target) && !suggestions.contains(e.target)) {
            suggestions.style.display = 'none';
        }
    });
}

// Inicializar autocompletes quando DOM estiver pronto
document.addEventListener('DOMContentLoaded', function() {
    // Autocomplete de cliente (criar OS)
    setupOsAutocomplete('createCustomerSearch', 'createCustomerSuggestions', osCustomers, {
        showDetails: true,
        hiddenInputId: 'createCustomerId'
    });

    // Autocomplete de cliente (editar OS)
    setupOsAutocomplete('editCustomerSearch', 'editCustomerSuggestions', osCustomers, {
        showDetails: true,
        hiddenInputId: 'edit_customer_id'
    });

    // Autocomplete de técnicos
    setupOsAutocomplete('createTechnicianSearch', 'createTechnicianSuggestions', osTechnicians, {});
    setupOsAutocomplete('edit_technician_name', 'editTechnicianSuggestions', osTechnicians, {});

    // Autocomplete de atendentes
    setupOsAutocomplete('createAttendantSearch', 'createAttendantSuggestions', osAttendants, {});
    setupOsAutocomplete('edit_attendant_name', 'editAttendantSuggestions', osAttendants, {});

    // Ao voltar para esta aba, atualiza lista de clientes automaticamente
    window.addEventListener('focus', function() {
        fetch('?action=get_customers')
            .then(r => r.json())
            .then(data => {
                osCustomers.length = 0;
                data.forEach(c => osCustomers.push(c));
                // Re-dispara busca se houver texto nos campos de cliente
                ['createCustomerSearch', 'editCustomerSearch'].forEach(function(id) {
                    const input = document.getElementById(id);
                    if (input && input.value.trim().length > 0) {
                        input.dispatchEvent(new Event('input'));
                    }
                });
            })
            .catch(function() {});
    });
});

// Gerenciamento de itens na OS (produtos + serviços)
let createItems = [];
let editItems = [];

function addProductToOS(mode) {
    const productId = document.getElementById(mode + '_selected_product_id').value;
    const productName = document.getElementById(mode + '_selected_product_name').value;
    const price = parseFloat(document.getElementById(mode + '_product_price_input').value);
    const stock = parseInt(document.getElementById(mode + '_selected_product_stock').value);
    const quantityInput = document.getElementById(mode + '_product_quantity');
    const quantity = parseInt(quantityInput.value);

    if (!productId) { showAlert('Selecione um produto', 'warning'); return; }
    if (!price || price <= 0) { showAlert('Informe o preço do produto', 'warning'); return; }
    if (stock <= 0) { showAlert('Produto sem estoque disponível!', 'warning'); return; }
    if (quantity > stock) { showAlert('Quantidade indisponível! Estoque: ' + stock, 'warning'); return; }

    const item = {
        type: 'product',
        id: productId,
        name: productName,
        price: price,
        quantity: quantity,
        subtotal: price * quantity
    };

    if (mode === 'create') { createItems.push(item); } else { editItems.push(item); }
    updateItemsList(mode);

    // Limpar seleção
    document.getElementById(mode + '_product_search').value = '';
    document.getElementById(mode + '_selected_product_id').value = '';
    document.getElementById(mode + '_selected_product_name').value = '';
    document.getElementById(mode + '_product_price_input').value = '';
    document.getElementById(mode + '_product_price_input').readOnly = false;
    document.getElementById(mode + '_product_price_input').style.background = '';
    document.getElementById(mode + '_selected_product_stock').value = '';
    quantityInput.value = 1;
}

function addServiceToOS(mode) {
    const serviceId = document.getElementById(mode + '_selected_service_id').value;
    const serviceName = document.getElementById(mode + '_selected_service_name').value;
    const price = parseFloat(document.getElementById(mode + '_service_price').value);
    const quantityInput = document.getElementById(mode + '_service_quantity');
    const quantity = parseInt(quantityInput.value);

    if (!serviceId) { showAlert('Selecione um serviço do catálogo', 'warning'); return; }
    if (!price || price <= 0) { showAlert('Informe o preço do serviço', 'warning'); return; }

    const item = {
        type: 'service',
        id: serviceId,
        name: serviceName,
        price: price,
        quantity: quantity,
        subtotal: price * quantity
    };

    if (mode === 'create') { createItems.push(item); } else { editItems.push(item); }
    updateItemsList(mode);

    // Limpar campos
    document.getElementById(mode + '_service_search').value = '';
    document.getElementById(mode + '_selected_service_id').value = '';
    document.getElementById(mode + '_selected_service_name').value = '';
    document.getElementById(mode + '_service_price').value = '';
    document.getElementById(mode + '_service_price').readOnly = false;
    document.getElementById(mode + '_service_price').style.background = '';
    quantityInput.value = 1;
}

function removeItemFromOS(mode, index) {
    if (mode === 'create') { createItems.splice(index, 1); } else { editItems.splice(index, 1); }
    updateItemsList(mode);
}

function updateItemsList(mode) {
    const items = mode === 'create' ? createItems : editItems;
    const listDiv = document.getElementById(mode + '_products_list');
    const dataInput = document.getElementById(mode + '_products_data');
    const totalInput = document.getElementById(mode + '_total_cost');

    if (items.length === 0) {
        listDiv.innerHTML = '<p style="color:#999;text-align:center;padding:15px;">Nenhum produto ou serviço adicionado</p>';
        dataInput.value = '[]';
        totalInput.value = 0;
        return;
    }

    let total = 0;
    let html = '<table class="table" style="margin-top:10px;font-size:0.9rem;">';
    html += '<thead><tr><th>Tipo</th><th>Descrição</th><th>Qtd</th><th>Preço Unit.</th><th>Subtotal</th><th></th></tr></thead><tbody>';

    items.forEach((item, index) => {
        total += item.subtotal;
        const icon = item.type === 'product' ? '<i class="fas fa-box" style="color:#667eea;"></i>' : '<i class="fas fa-tools" style="color:#4CAF50;"></i>';
        html += '<tr>' +
            '<td>' + icon + '</td>' +
            '<td>' + item.name + '</td>' +
            '<td>' + item.quantity + '</td>' +
            '<td>R$ ' + item.price.toFixed(2).replace('.', ',') + '</td>' +
            '<td><strong>R$ ' + item.subtotal.toFixed(2).replace('.', ',') + '</strong></td>' +
            '<td><button type="button" class="btn btn-danger btn-sm" onclick="removeItemFromOS(\'' + mode + '\',' + index + ')"><i class="fas fa-trash"></i></button></td>' +
            '</tr>';
    });

    html += '</tbody></table>';
    html += '<div style="text-align:right;font-weight:bold;font-size:16px;color:#667eea;margin-top:10px;">' +
        'Total: R$ ' + total.toFixed(2).replace('.', ',') +
    '</div>';

    listDiv.innerHTML = html;
    dataInput.value = JSON.stringify(items);

    // Aplicar desconto ao total
    const discount = parseFloat(document.getElementById(mode + '_discount').value) || 0;
    const totalWithDiscount = Math.max(0, total - discount);
    totalInput.value = totalWithDiscount.toFixed(2);

    updateFinancialBalance(mode);
}

// ===== MÚLTIPLAS FORMAS DE PAGAMENTO =====
let createPayments = [];
let editPayments = [];

function traduzirMetodo(method) {
    const map = {
        'dinheiro': 'Dinheiro', 'pix': 'PIX',
        'cartao_credito': 'Cartão Crédito', 'cartao_debito': 'Cartão Débito'
    };
    return map[method] || method;
}

function togglePaymentExtras(mode) {
    const method = document.getElementById(mode + '_new_payment_method').value;
    const installGroup = document.getElementById(mode + '_installments_group');
    const changeGroup = document.getElementById(mode + '_change_group');
    installGroup.style.display = method === 'cartao_credito' ? '' : 'none';
    changeGroup.style.display = method === 'dinheiro' ? '' : 'none';
    if (method === 'dinheiro') calculateChange(mode);
}

function calculateChange(mode) {
    const totalGeral = parseFloat(document.getElementById(mode + '_total_cost').value) || 0;
    const deposit = parseFloat((document.getElementById(mode + '_deposit_input') || document.getElementById(mode + '_deposit_amount')).value) || 0;
    const payments = mode === 'create' ? createPayments : editPayments;
    const paidSoFar = payments.reduce((sum, p) => sum + p.amount, 0) + deposit;
    const remaining = totalGeral - paidSoFar;
    const amountInput = document.getElementById(mode + '_new_payment_amount');
    const amountVal = parseFloat(amountInput.value) || 0;
    const change = amountVal > remaining && remaining > 0 ? amountVal - remaining : 0;
    const changeDisplay = document.getElementById(mode + '_change_display');
    if (changeDisplay) changeDisplay.value = change > 0 ? 'R$ ' + change.toFixed(2).replace('.', ',') : '';
}

function addPaymentToOS(mode) {
    const method = document.getElementById(mode + '_new_payment_method').value;
    const amount = parseFloat(document.getElementById(mode + '_new_payment_amount').value);
    const installments = method === 'cartao_credito' ? parseInt(document.getElementById(mode + '_new_payment_installments').value) || 1 : 1;

    if (!amount || amount <= 0) { showAlert('Informe o valor do pagamento', 'warning'); return; }

    const payments = mode === 'create' ? createPayments : editPayments;

    // Calcular troco para dinheiro
    let change = 0;
    if (method === 'dinheiro') {
        const totalGeral = parseFloat(document.getElementById(mode + '_total_cost').value) || 0;
        const deposit = parseFloat((document.getElementById(mode + '_deposit_input') || document.getElementById(mode + '_deposit_amount')).value) || 0;
        const paidSoFar = payments.reduce((sum, p) => sum + p.amount, 0) + deposit;
        const remaining = totalGeral - paidSoFar;
        if (amount > remaining && remaining > 0) {
            change = amount - remaining;
        }
    }

    payments.push({ method: method, amount: amount, installments: installments, change: change });
    renderPayments(mode);
    updateFinancialBalance(mode);

    // Limpar campos
    document.getElementById(mode + '_new_payment_amount').value = '';
    document.getElementById(mode + '_change_display').value = '';
    document.getElementById(mode + '_new_payment_method').value = 'dinheiro';
    document.getElementById(mode + '_new_payment_installments').value = '1';
    togglePaymentExtras(mode);
}

function removePaymentRow(mode, index) {
    const payments = mode === 'create' ? createPayments : editPayments;
    payments.splice(index, 1);
    renderPayments(mode);
    updateFinancialBalance(mode);
}

function renderPayments(mode) {
    const payments = mode === 'create' ? createPayments : editPayments;
    const listDiv = document.getElementById(mode + '_payments_list');

    if (payments.length === 0) {
        listDiv.innerHTML = '<p style="color:#999;font-size:13px;padding:5px 0;text-align:center;">Nenhuma forma de pagamento adicionada</p>';
        document.getElementById(mode + '_payment_methods_data').value = '[]';
        return;
    }

    let html = '<table class="table" style="margin-top:10px;font-size:0.9rem;">';
    html += '<thead><tr><th>Forma</th><th>Valor</th><th>Parcelas</th><th>Troco</th><th></th></tr></thead><tbody>';

    payments.forEach((p, index) => {
        const methodIcon = p.method === 'dinheiro' ? 'fa-money-bill-wave' :
                          p.method === 'pix' ? 'fa-qrcode' :
                          p.method.includes('credito') ? 'fa-credit-card' : 'fa-credit-card';
        const methodColor = p.method === 'dinheiro' ? '#4CAF50' :
                           p.method === 'pix' ? '#9C27B0' :
                           p.method.includes('credito') ? '#FF9800' : '#2196F3';
        html += '<tr>' +
            '<td><i class="fas ' + methodIcon + '" style="color:' + methodColor + ';"></i> ' + traduzirMetodo(p.method) + '</td>' +
            '<td><strong>R$ ' + p.amount.toFixed(2).replace('.', ',') + '</strong></td>' +
            '<td>' + (p.method === 'cartao_credito' ? p.installments + 'x' : '-') + '</td>' +
            '<td>' + (p.change > 0 ? '<span style="color:#4CAF50;font-weight:bold;">R$ ' + p.change.toFixed(2).replace('.', ',') + '</span>' : '-') + '</td>' +
            '<td><button type="button" class="btn btn-danger btn-sm" onclick="removePaymentRow(\'' + mode + '\',' + index + ')"><i class="fas fa-trash"></i></button></td>' +
            '</tr>';
    });

    html += '</tbody></table>';
    listDiv.innerHTML = html;
    document.getElementById(mode + '_payment_methods_data').value = JSON.stringify(payments);
}

function applyDiscount(mode) {
    const items = mode === 'create' ? createItems : editItems;
    let subtotal = 0;
    items.forEach(item => { subtotal += item.subtotal; });

    const discount = parseFloat(document.getElementById(mode + '_discount').value) || 0;
    const totalFinal = Math.max(0, subtotal - discount);
    document.getElementById(mode + '_total_cost').value = totalFinal.toFixed(2);

    // Atualizar percentual correspondente
    const percentInput = document.getElementById(mode + '_discount_percent');
    if (subtotal > 0 && discount > 0) {
        percentInput.value = ((discount / subtotal) * 100).toFixed(1);
    } else {
        percentInput.value = '';
    }

    updateFinancialBalance(mode);
}

function applyDiscountPercent(mode) {
    const items = mode === 'create' ? createItems : editItems;
    let subtotal = 0;
    items.forEach(item => { subtotal += item.subtotal; });

    const percent = parseFloat(document.getElementById(mode + '_discount_percent').value) || 0;
    const discount = (subtotal * percent) / 100;
    document.getElementById(mode + '_discount').value = discount.toFixed(2);

    const totalFinal = Math.max(0, subtotal - discount);
    document.getElementById(mode + '_total_cost').value = totalFinal.toFixed(2);

    updateFinancialBalance(mode);
}

function updateFinancialBalance(mode) {
    const items = mode === 'create' ? createItems : editItems;
    const payments = mode === 'create' ? createPayments : editPayments;

    // Calcular totais por tipo
    let totalProducts = 0, totalServices = 0;
    items.forEach(item => {
        if (item.type === 'product') totalProducts += item.subtotal;
        else totalServices += item.subtotal;
    });

    // Desconto
    const discount = parseFloat(document.getElementById(mode + '_discount').value) || 0;

    // Total geral (editável)
    const totalGeral = parseFloat(document.getElementById(mode + '_total_cost').value) || 0;

    // Entrada/sinal
    const depositInput = document.getElementById(mode + '_deposit_input') || document.getElementById(mode + '_deposit_amount');
    const deposit = parseFloat(depositInput.value) || 0;

    // Total pagamentos (sem contar troco)
    const totalPayments = payments.reduce((sum, p) => sum + (p.amount - (p.change || 0)), 0);

    // Restante
    const remaining = totalGeral - deposit - totalPayments;

    // Atualizar displays
    const fmt = (v) => 'R$ ' + v.toFixed(2).replace('.', ',');
    document.getElementById(mode + '_total_products_display').textContent = fmt(totalProducts);
    document.getElementById(mode + '_total_services_display').textContent = fmt(totalServices);
    document.getElementById(mode + '_discount_display').textContent = '- ' + fmt(discount);
    document.getElementById(mode + '_deposit_display').textContent = fmt(deposit);
    document.getElementById(mode + '_payments_total_display').textContent = fmt(totalPayments);

    const remainingEl = document.getElementById(mode + '_remaining_display');
    remainingEl.textContent = fmt(Math.abs(remaining));
    if (remaining <= 0) {
        remainingEl.style.color = '#4CAF50';
        remainingEl.textContent = remaining === 0 ? 'R$ 0,00' : fmt(Math.abs(remaining)) + ' (excedente)';
    } else {
        remainingEl.style.color = '#f44336';
    }

    // Atualizar barra de totais no rodapé
    const bruto = totalProducts + totalServices;
    const total = Math.max(0, bruto - discount);
    const totalChange = payments.reduce((sum, p) => sum + (p.change || 0), 0);
    const totalPago = deposit + totalPayments;
    const footerBruto = document.getElementById(mode + '_footer_bruto');
    const footerDesconto = document.getElementById(mode + '_footer_desconto');
    const footerLiquido = document.getElementById(mode + '_footer_liquido');
    const footerItens = document.getElementById(mode + '_footer_itens');
    const footerPago = document.getElementById(mode + '_footer_pago');
    const footerTroco = document.getElementById(mode + '_footer_troco');
    if (footerBruto) footerBruto.textContent = fmt(bruto);
    if (footerDesconto) footerDesconto.textContent = fmt(discount);
    if (footerLiquido) footerLiquido.textContent = fmt(total);
    if (footerItens) footerItens.textContent = items.reduce((s, i) => s + i.quantity, 0);
    if (footerPago) footerPago.textContent = fmt(totalPago);
    if (footerTroco) footerTroco.textContent = fmt(totalChange);
}

function switchTab(mode, tab) {
    document.getElementById(mode + '_tab_products').style.display = tab === 'products' ? 'flex' : 'none';
    document.getElementById(mode + '_tab_payment').style.display = tab === 'payment' ? 'block' : 'none';
    document.getElementById(mode + '_tab_products_btn').style.background = tab === 'products' ? 'linear-gradient(45deg,#667eea,#764ba2)' : '#e9ecef';
    document.getElementById(mode + '_tab_products_btn').style.color = tab === 'products' ? 'white' : '#666';
    document.getElementById(mode + '_tab_payment_btn').style.background = tab === 'payment' ? 'linear-gradient(45deg,#667eea,#764ba2)' : '#e9ecef';
    document.getElementById(mode + '_tab_payment_btn').style.color = tab === 'payment' ? 'white' : '#666';
}

function openEditModal(o){
    // Dados básicos
    document.getElementById('edit_id').value=o.id;
    document.getElementById('edit_customer_id').value=o.customer_id||'';
    const customer = osCustomers.find(c => c.id == o.customer_id);
    document.getElementById('editCustomerSearch').value = customer ? customer.name : (o.customer_name || '');
    document.getElementById('edit_device').value=o.device||'';
    document.getElementById('edit_device_type').value=o.device_type||'celular';
    document.getElementById('edit_status').value=o.status||'open';
    document.getElementById('edit_total_cost').value=o.total_cost||0;
    document.getElementById('edit_discount').value=o.discount||0;
    document.getElementById('edit_discount_percent').value='';
    document.getElementById('edit_deposit_amount').value=o.deposit_amount||0;

    // Formas de pagamento (múltiplas)
    editPayments = [];
    if (o.payment_methods) {
        try {
            editPayments = JSON.parse(o.payment_methods);
            // Garantir que cada pagamento tenha o campo change
            editPayments.forEach(p => { if (!p.change) p.change = 0; });
        } catch(e) { editPayments = []; }
    }
    // Fallback: se não tem payment_methods JSON mas tem payment_method antigo
    if (editPayments.length === 0 && o.payment_method) {
        editPayments = [{ method: o.payment_method, amount: parseFloat(o.total_cost) || 0, installments: parseInt(o.installments) || 1, change: 0 }];
    }
    renderPayments('edit');
    updateFinancialBalance('edit');

    // Diagnóstico
    document.getElementById('edit_reported_problem').value=o.reported_problem||'';
    document.getElementById('edit_device_powers_on').value=o.device_powers_on||'sim';
    document.getElementById('edit_device_password').value=o.device_password||'';
    document.getElementById('edit_technical_report').value=o.technical_report||'';

    // Senha desenho (pattern)
    clearPattern('edit');
    if (o.password_pattern) {
        document.getElementById('editPasswordPattern').value = o.password_pattern;
        const dots = o.password_pattern.split(',');
        dots.forEach(d => {
            const dot = document.querySelector('#editPatternGrid .pattern-dot[data-dot="'+d+'"]');
            if (dot) dot.classList.add('active');
        });
    }

    // Foto do aparelho
    const previewDiv = document.getElementById('edit_os_image_preview');
    document.getElementById('edit_remove_os_image').value = '';
    if (o.image) {
        document.getElementById('edit_os_image_thumb').src = '../../uploads/service_orders/' + o.image;
        previewDiv.style.display = 'block';
    } else {
        previewDiv.style.display = 'none';
    }

    // Checklist
    document.getElementById('edit_checklist_lens').checked=o.checklist_lens==1;
    document.getElementById('edit_checklist_back_cover').checked=!!(o.checklist_back_cover);
    document.getElementById('edit_checklist_screen').checked=o.checklist_screen==1;
    document.getElementById('edit_checklist_connector').checked=o.checklist_connector==1;
    document.getElementById('edit_checklist_camera_front_back').checked=!!(o.checklist_camera_front_back);
    document.getElementById('edit_checklist_face_id').checked=o.checklist_face_id==1;
    document.getElementById('edit_checklist_sim_card').checked=o.checklist_sim_card==1;

    // Observações
    document.getElementById('edit_customer_observations').value=o.customer_observations||'';
    document.getElementById('edit_internal_observations').value=o.internal_observations||'';

    // Responsáveis
    document.getElementById('edit_technician_name').value=o.technician_name||'';
    document.getElementById('edit_attendant_name').value=o.attendant_name||'';

    // Garantia
    document.getElementById('edit_warranty_period').value=o.warranty_period||'90 dias';

    // Limpar itens e carregar da OS
    editItems = [];
    loadOSItems(o.id);

    openModal('editModal');
}

// Carregar itens (produtos + serviços) de uma OS existente
async function loadOSItems(osId) {
    try {
        const response = await fetch('get_os_products.php?os_id=' + osId);
        const data = await response.json();

        if (data.success && data.products.length > 0) {
            editItems = data.products.map(p => ({
                type: p.type || 'product',
                id: p.product_id,
                name: p.product_name || p.description,
                price: parseFloat(p.unit_price),
                quantity: parseInt(p.quantity),
                subtotal: parseFloat(p.total_price)
            }));
            updateItemsList('edit');
        } else {
            editItems = [];
            updateItemsList('edit');
        }
    } catch (error) {
        console.error('Erro ao carregar itens:', error);
        editItems = [];
        updateItemsList('edit');
    }
}

function openUnlockModal(osId) {
    document.getElementById('unlock_os_id').value = osId;
    document.getElementById('unlock_username').value = '';
    document.getElementById('unlock_password').value = '';
    openModal('unlockModal');
}

async function unlockServiceOrder(event) {
    event.preventDefault();

    const osId = document.getElementById('unlock_os_id').value;
    const username = document.getElementById('unlock_username').value;
    const password = document.getElementById('unlock_password').value;

    const formData = new FormData();
    formData.append('action', 'unlock');
    formData.append('os_id', osId);
    formData.append('username', username);
    formData.append('password', password);

    try {
        const response = await fetch('index.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            showAlert(data.message, 'success');
            closeModal('unlockModal');
            window.location.reload();
        } else {
            showAlert(data.message, 'error');
        }
    } catch (error) {
        showAlert('Erro ao desbloquear OS: ' + error.message, 'error');
    }
}

function printServiceOrder(order) {
    // Buscar dados do cliente e termos de garantia
    const customerId = order.customer_id;

    Promise.all([
        fetch(`../../modules/customers/get_customer.php?id=${customerId}`).then(r => r.json()),
        fetch('get_warranty_terms.php').then(r => r.json())
    ])
    .then(([customer, warrantyTerms]) => {
        generateServiceOrderPrint(order, customer, warrantyTerms);
    })
    .catch(error => {
        console.error('Erro ao buscar dados:', error);
        showAlert('Erro ao carregar dados para impressão', 'error');
    });
}

function generateServiceOrderPrint(order, customer, warrantyTerms) {
    const printWindow = window.open('', '_blank');

    // Traduzir status
    const statusMap = {
        'open': 'Aguardando',
        'in_progress': 'Em Andamento',
        'completed': 'Concluído',
        'invoiced': 'Faturada',
        'delivered': 'Entregue',
        'cancelled': 'Cancelado'
    };

    // Traduzir forma de pagamento
    const paymentMap = {
        'dinheiro': 'Dinheiro',
        'pix': 'PIX',
        'cartao_credito': 'Cartão de Crédito',
        'cartao_debito': 'Cartão de Débito'
    };

    // Formatar valor
    const totalFormatted = new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(order.total_cost || 0);

    // Formatar data
    const dateFormatted = new Date(order.entry_datetime).toLocaleDateString('pt-BR');

    // Substituir [PERIODO_GARANTIA] no texto da garantia
    const warrantyPeriod = order.warranty_period || '90 dias';

    // Conteúdo de uma via (será duplicado)
    const viaContent = `
    <div class="header">
        <div class="header-content">
            <div class="header-left">
                <div class="logo">
                    <i class="fas fa-mobile-alt"></i> FL REPAROS
                </div>
                <div class="subtitle">Sistema de Gestão para Assistência Técnica</div>
                <div class="os-number">OS #${order.id}</div>
                <span class="status-badge status-${order.status}">${statusMap[order.status] || order.status}</span>
            </div>
            <div class="password-box">
                <div class="password-label">Senha/Padrão</div>
                <div class="password-text">${order.device_password || ''}</div>
                <div class="pattern-grid">
                    ${[1,2,3,4,5,6,7,8,9].map(i => {
                        const isActive = order.password_pattern && order.password_pattern.split(',').includes(String(i));
                        return `<div class="pattern-dot" style="${isActive ? 'background:#667eea;border-color:#667eea;' : ''}"></div>`;
                    }).join('')}
                </div>
                <div class="password-footer">Desenhe o Padrão:</div>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title"><i class="fas fa-user"></i> Cliente</div>
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Nome</div>
                <div class="info-value">${customer.name || '-'}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Telefone</div>
                <div class="info-value">${customer.phone || '-'}</div>
            </div>
            ${customer.cpf_cnpj ? `
            <div class="info-item">
                <div class="info-label">CPF/CNPJ</div>
                <div class="info-value">${customer.cpf_cnpj}</div>
            </div>
            ` : ''}
            ${customer.address ? `
            <div class="info-item">
                <div class="info-label">Endereço</div>
                <div class="info-value">${customer.address}${customer.neighborhood ? ' - ' + customer.neighborhood : ''}</div>
            </div>
            ` : ''}
        </div>
    </div>

    <div class="section">
        <div class="section-title"><i class="fas fa-mobile-alt"></i> Equipamento</div>
        <div class="info-grid">
            ${order.device_type ? `
            <div class="info-item">
                <div class="info-label">Tipo</div>
                <div class="info-value">${order.device_type.charAt(0).toUpperCase() + order.device_type.slice(1)}</div>
            </div>
            ` : ''}
            <div class="info-item">
                <div class="info-label">Modelo</div>
                <div class="info-value">${order.device || '-'}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Data de Entrada</div>
                <div class="info-value">${dateFormatted}</div>
            </div>
            ${order.exit_datetime ? `
            <div class="info-item">
                <div class="info-label">Data de Saída</div>
                <div class="info-value">${new Date(order.exit_datetime).toLocaleDateString('pt-BR')} ${new Date(order.exit_datetime).toLocaleTimeString('pt-BR', {hour:'2-digit',minute:'2-digit'})}</div>
            </div>
            ` : ''}
            <div class="info-item">
                <div class="info-label">Liga?</div>
                <div class="info-value">${order.device_powers_on === 'sim' ? 'Sim' : 'Não'}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Valor Total</div>
                <div class="info-value" style="color: #28a745; font-weight: bold;">${totalFormatted}</div>
            </div>
            ${parseFloat(order.deposit_amount) > 0 ? `
            <div class="info-item">
                <div class="info-label">Entrada/Sinal</div>
                <div class="info-value" style="color: #2196F3;">R$ ${parseFloat(order.deposit_amount).toFixed(2).replace('.', ',')}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Restante</div>
                <div class="info-value" style="color: #f44336;">R$ ${(parseFloat(order.total_cost || 0) - parseFloat(order.deposit_amount)).toFixed(2).replace('.', ',')}</div>
            </div>
            ` : ''}
            ${(() => {
                let pmHtml = '';
                let payments = [];
                if (order.payment_methods) {
                    try { payments = JSON.parse(order.payment_methods); } catch(e) {}
                }
                if (payments.length > 0) {
                    payments.forEach((pm, i) => {
                        const label = payments.length > 1 ? 'Pagamento ' + (i+1) : 'Forma de Pagamento';
                        let val = (paymentMap[pm.method] || pm.method) + ' - R$ ' + parseFloat(pm.amount).toFixed(2).replace('.', ',');
                        if (pm.method === 'cartao_credito' && pm.installments > 1) {
                            val += ' (' + pm.installments + 'x)';
                        }
                        pmHtml += '<div class="info-item"><div class="info-label">' + label + '</div><div class="info-value">' + val + '</div></div>';
                    });
                } else if (order.payment_method) {
                    pmHtml += '<div class="info-item"><div class="info-label">Forma de Pagamento</div><div class="info-value">' + (paymentMap[order.payment_method] || order.payment_method) + '</div></div>';
                }
                return pmHtml;
            })()}
        </div>
    </div>

    ${order.reported_problem ? `
    <div class="section">
        <div class="section-title"><i class="fas fa-exclamation-circle"></i> Problema</div>
        <div class="text-area">${order.reported_problem}</div>
    </div>
    ` : ''}

    ${order.technical_report ? `
    <div class="section">
        <div class="section-title"><i class="fas fa-wrench"></i> Laudo</div>
        <div class="text-area">${order.technical_report}</div>
    </div>
    ` : ''}

    ${(order.checklist_lens || order.checklist_back_cover || order.checklist_screen || order.checklist_connector || order.checklist_camera_front_back || order.checklist_face_id || order.checklist_sim_card) ? `
    <div class="section">
        <div class="section-title"><i class="fas fa-clipboard-check"></i> Checklist</div>
        <div class="checklist">
            ${order.checklist_lens ? '<div class="checklist-item"><i class="fas fa-check-circle"></i> Lente</div>' : ''}
            ${order.checklist_screen ? '<div class="checklist-item"><i class="fas fa-exclamation-triangle"></i> Tela Trincada</div>' : ''}
            ${order.checklist_sim_card ? '<div class="checklist-item"><i class="fas fa-check-circle"></i> Chip</div>' : ''}
            ${order.checklist_back_cover ? '<div class="checklist-item"><i class="fas fa-check-circle"></i> Tampa Traseira</div>' : ''}
            ${order.checklist_face_id ? '<div class="checklist-item"><i class="fas fa-times-circle"></i> Sem Face ID</div>' : ''}
            ${order.checklist_connector ? '<div class="checklist-item"><i class="fas fa-check-circle"></i> Conector</div>' : ''}
            ${order.checklist_camera_front_back ? '<div class="checklist-item"><i class="fas fa-camera"></i> Câmera</div>' : ''}
        </div>
    </div>
    ` : ''}

    ${order.customer_observations ? `
    <div class="section">
        <div class="section-title"><i class="fas fa-comment-alt"></i> Observações</div>
        <div class="text-area">${order.customer_observations}</div>
    </div>
    ` : ''}

    ${order.technician_name || order.attendant_name ? `
    <div class="section">
        <div class="section-title"><i class="fas fa-users"></i> Responsáveis</div>
        <div class="info-grid">
            ${order.technician_name ? `
            <div class="info-item">
                <div class="info-label">Técnico</div>
                <div class="info-value">${order.technician_name}</div>
            </div>
            ` : ''}
            ${order.attendant_name ? `
            <div class="info-item">
                <div class="info-label">Atendente</div>
                <div class="info-value">${order.attendant_name}</div>
            </div>
            ` : ''}
        </div>
    </div>
    ` : ''}

    ${warrantyPeriod !== 'Sem garantia' ? `
    <div class="warranty-section">
        <div class="warranty-title">⚠️ ${warrantyTerms.title}</div>
        ${warrantyTerms.clauses.map(clause => `
        <div class="warranty-clause">
            <strong>${clause.number}.</strong> ${clause.text.replace('[PERIODO_GARANTIA]', warrantyPeriod)}
        </div>
        `).join('')}
        <div class="warranty-footer">
            ${warrantyTerms.footer}
        </div>
    </div>
    ` : ''}

    <div class="signature-area">
        <div class="signature-line">
            Assinatura do Cliente
        </div>
    </div>

    <div class="footer">
        <p><strong>FL REPAROS</strong></p>
        <p>${new Date().toLocaleDateString('pt-BR')} ${new Date().toLocaleTimeString('pt-BR')}</p>
    </div>
`;

    const htmlContent = `
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Ordem de Serviço #${order.id}</title>
    <style>
        @media print {
            @page {
                size: A4;
                margin: 5mm;
            }
            body { margin: 0; padding: 0; }
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            padding: 0;
            background: white;
            height: 100vh;
        }
        .container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            width: 100%;
            height: 100%;
            gap: 6px;
        }
        .via {
            border: 2px solid #667eea;
            padding: 6px;
            font-size: 10px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        @media print {
            .container {
                page-break-after: avoid;
                height: 100vh;
            }
            .via {
                page-break-inside: avoid;
                height: 100%;
            }
        }
        .header {
            margin-bottom: 5px;
            border-bottom: 2px solid #667eea;
            padding-bottom: 4px;
        }
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
        }
        .header-left {
            flex: 1;
            text-align: center;
        }
        .logo {
            font-size: 16px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 2px;
        }
        .logo i {
            margin-right: 4px;
        }
        .subtitle {
            color: #666;
            font-size: 8px;
        }
        .os-number {
            font-size: 14px;
            font-weight: bold;
            color: #333;
            margin: 3px 0;
        }
        .password-box {
            border: 2px solid #333;
            border-radius: 5px;
            padding: 6px;
            background: white;
            min-width: 90px;
            max-width: 90px;
        }
        .password-label {
            font-size: 8px;
            font-weight: bold;
            color: #333;
            text-align: center;
            margin-bottom: 3px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 2px;
        }
        .password-text {
            font-size: 9px;
            font-weight: bold;
            text-align: center;
            color: #333;
            min-height: 14px;
            margin-bottom: 4px;
            word-break: break-all;
        }
        .pattern-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            padding: 8px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 3px;
            margin-bottom: 3px;
        }
        .pattern-dot {
            width: 10px;
            height: 10px;
            border: 2px solid #333;
            border-radius: 50%;
            background: white;
            margin: auto;
        }
        .password-footer {
            font-size: 6px;
            color: #666;
            text-align: center;
            font-style: italic;
        }
        .section {
            margin-bottom: 5px;
        }
        .section-title {
            background: #667eea;
            color: white;
            padding: 3px 6px;
            font-weight: bold;
            margin-bottom: 3px;
            border-radius: 3px;
            font-size: 9px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 4px;
        }
        .info-grid.single-column {
            grid-template-columns: 1fr;
        }
        .info-item {
            padding: 3px 5px;
            background: #f5f5f5;
            border-left: 3px solid #667eea;
        }
        .info-label {
            font-size: 7.5px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 1px;
            font-weight: 600;
        }
        .info-value {
            font-size: 10px;
            color: #333;
            font-weight: 600;
            line-height: 1.3;
        }
        .checklist {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 3px;
            margin-top: 4px;
        }
        .checklist-item {
            padding: 3px 5px;
            background: #f9f9f9;
            border-radius: 3px;
            display: flex;
            align-items: center;
            font-size: 8.5px;
        }
        .checklist-item i {
            margin-right: 3px;
            color: #28a745;
            font-size: 8.5px;
        }
        .text-area {
            background: #f9f9f9;
            padding: 5px;
            border-radius: 3px;
            min-height: 25px;
            margin-top: 4px;
            white-space: pre-wrap;
            font-size: 8.5px;
            line-height: 1.4;
        }
        .warranty-section {
            margin-top: 6px;
            padding: 5px;
            background: #fffbf0;
            border: 1.5px solid #ffc107;
            border-radius: 3px;
        }
        .warranty-title {
            font-size: 9px;
            font-weight: bold;
            color: #856404;
            text-align: center;
            margin-bottom: 3px;
        }
        .warranty-clause {
            font-size: 7px;
            line-height: 1.5;
            margin-bottom: 2px;
            text-align: justify;
        }
        .warranty-footer {
            font-size: 7px;
            text-align: center;
            margin-top: 3px;
            font-style: italic;
            color: #856404;
        }
        .signature-area {
            margin-top: 8px;
            padding-top: 5px;
            border-top: 1px dashed #ccc;
        }
        .signature-line {
            border-top: 1.5px solid #333;
            margin: 8px 10px 2px;
            padding-top: 2px;
            text-align: center;
            color: #666;
            font-size: 8px;
        }
        .footer {
            text-align: center;
            margin-top: 5px;
            padding-top: 3px;
            border-top: 1.5px solid #667eea;
            color: #666;
            font-size: 7px;
        }
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 5px;
            font-size: 8.5px;
            font-weight: bold;
        }
        .via-label {
            text-align: center;
            font-size: 10px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        .status-open { background: #ffc107; color: #000; }
        .status-in_progress { background: #17a2b8; color: white; }
        .status-completed { background: #28a745; color: white; }
        .status-invoiced { background: #00BCD4; color: white; }
        .status-delivered { background: #6c757d; color: white; }
        .status-cancelled { background: #dc3545; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <!-- VIA 1 - CLIENTE -->
        <div class="via">
            <div class="via-label">📋 VIA DO CLIENTE</div>
            ${viaContent}
        </div>

        <!-- VIA 2 - LOJA -->
        <div class="via">
            <div class="via-label">🏪 VIA DA LOJA</div>
            ${viaContent}
        </div>
    </div>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</body>
</html>
    `;

    printWindow.document.write(htmlContent);
    printWindow.document.close();

    // Aguardar carregar e imprimir
    printWindow.onload = function() {
        setTimeout(() => {
            printWindow.print();
        }, 250);
    };
}

// Fechar ao clicar fora apenas para modais que não são OS
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        const id = event.target.id;
        if (id !== 'createModal' && id !== 'editModal') {
            event.target.style.display = 'none';
        }
    }
}

// ESC fecha apenas modais que não são OS
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal('unlockModal');
    }
});

function confirmCloseOS(id) {
    showConfirm('Tem certeza que deseja fechar? As informações preenchidas serão perdidas.', 'Fechar', 'Fechar', 'Cancelar', 'warning').then(ok => {
        if (ok) closeModal(id);
    });
}

// Função para mudar registros por página
function changePerPage(perPage) {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('per_page', perPage);
    urlParams.set('page', 1);
    window.location.search = urlParams.toString();
}

// Senha Desenho (Pattern Lock)
function toggleDot(el, mode) {
    el.classList.toggle('active');
    updatePattern(mode);
}

function clearPattern(mode) {
    const gridId = mode === 'create' ? 'createPatternGrid' : 'editPatternGrid';
    const inputId = mode === 'create' ? 'createPasswordPattern' : 'editPasswordPattern';
    document.querySelectorAll('#' + gridId + ' .pattern-dot').forEach(d => d.classList.remove('active'));
    document.getElementById(inputId).value = '';
}

function updatePattern(mode) {
    const gridId = mode === 'create' ? 'createPatternGrid' : 'editPatternGrid';
    const inputId = mode === 'create' ? 'createPasswordPattern' : 'editPasswordPattern';
    const activeDots = [];
    document.querySelectorAll('#' + gridId + ' .pattern-dot.active').forEach(d => {
        activeDots.push(d.dataset.dot);
    });
    document.getElementById(inputId).value = activeDots.join(',');
}

// Remover foto da OS (edição)
function removeOsImage(mode) {
    document.getElementById(mode + '_remove_os_image').value = '1';
    document.getElementById(mode + '_os_image_preview').style.display = 'none';
}

// ---- CADASTRO RÁPIDO DE CLIENTE ----
let _quickCustomerContext = 'create'; // 'create' ou 'edit'

function openQuickCustomerModal(e) {
    e.preventDefault();
    // Detecta contexto pelo modal aberto
    const editOpen = document.getElementById('editModal').style.display === 'block';
    _quickCustomerContext = editOpen ? 'edit' : 'create';
    document.getElementById('quickCustomerForm').reset();
    document.getElementById('quickCustomerError').style.display = 'none';
    openModal('quickCustomerModal');
}

async function saveQuickCustomer(e) {
    e.preventDefault();
    const form = document.getElementById('quickCustomerForm');
    const data = new FormData(form);
    data.append('action', 'quick_add_customer');
    const errEl = document.getElementById('quickCustomerError');
    errEl.style.display = 'none';
    try {
        const resp = await fetch('', { method: 'POST', body: data });
        const json = await resp.json();
        if (!json.success) { errEl.textContent = json.error || 'Erro ao salvar.'; errEl.style.display = 'block'; return; }
        // Preenche o campo de cliente no contexto certo
        const searchId = _quickCustomerContext === 'edit' ? 'editCustomerSearch' : 'createCustomerSearch';
        const hiddenId = _quickCustomerContext === 'edit' ? 'edit_customer_id' : 'createCustomerId';
        document.getElementById(searchId).value = json.name + (json.cpf_cnpj ? ' — ' + json.cpf_cnpj : '') + (json.phone ? ' | ' + json.phone : '');
        document.getElementById(hiddenId).value = json.id;
        closeModal('quickCustomerModal');
    } catch(err) { errEl.textContent = 'Erro de comunicação.'; errEl.style.display = 'block'; }
}
</script>

<!-- Modal: Cadastro Rápido de Cliente -->
<div id="quickCustomerModal" class="modal">
    <div class="modal-content" style="max-width:1100px;width:96%;margin:20px auto;">
        <div class="modal-header">
            <h2><i class="fas fa-user-plus"></i> Novo Cliente</h2>
            <button class="close" onclick="closeModal('quickCustomerModal')">&times;</button>
        </div>
        <form id="quickCustomerForm" onsubmit="saveQuickCustomer(event)" style="padding:10px 0;">
            <!-- Linha 1: Nome | CPF/CNPJ | Telefone -->
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:30px;margin-bottom:20px;">
                <div>
                    <label style="display:block;margin-bottom:5px;font-weight:600;color:#333;">Nome Completo *</label>
                    <input type="text" name="name" class="form-control" required placeholder="Nome completo do cliente">
                </div>
                <div>
                    <label style="display:block;margin-bottom:5px;font-weight:600;color:#333;">CPF/CNPJ</label>
                    <input type="text" name="cpf_cnpj" class="form-control" placeholder="000.000.000-00">
                </div>
                <div>
                    <label style="display:block;margin-bottom:5px;font-weight:600;color:#333;">Telefone</label>
                    <input type="text" name="phone" class="form-control" placeholder="(00) 00000-0000">
                </div>
            </div>
            <!-- Linha 2: Email | Data de Nascimento | CEP -->
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:30px;margin-bottom:20px;">
                <div>
                    <label style="display:block;margin-bottom:5px;font-weight:600;color:#333;">Email</label>
                    <input type="email" name="email" class="form-control" placeholder="email@exemplo.com">
                </div>
                <div>
                    <label style="display:block;margin-bottom:5px;font-weight:600;color:#333;">Data de Nascimento</label>
                    <input type="date" name="birth_date" class="form-control">
                </div>
                <div>
                    <label style="display:block;margin-bottom:5px;font-weight:600;color:#333;">CEP</label>
                    <input type="text" name="zipcode" class="form-control" placeholder="00000-000">
                </div>
            </div>
            <!-- Linha 3: Endereço | Cidade | Estado -->
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:30px;margin-bottom:20px;">
                <div>
                    <label style="display:block;margin-bottom:5px;font-weight:600;color:#333;">Endereço</label>
                    <input type="text" name="address" class="form-control" placeholder="Rua, número, complemento">
                </div>
                <div>
                    <label style="display:block;margin-bottom:5px;font-weight:600;color:#333;">Cidade</label>
                    <input type="text" name="city" class="form-control" placeholder="Nome da cidade">
                </div>
                <div>
                    <label style="display:block;margin-bottom:5px;font-weight:600;color:#333;">Estado</label>
                    <select name="state" class="form-control">
                        <option value="">Selecione</option>
                        <option value="AC">AC</option><option value="AL">AL</option><option value="AP">AP</option>
                        <option value="AM">AM</option><option value="BA">BA</option><option value="CE">CE</option>
                        <option value="DF">DF</option><option value="ES">ES</option><option value="GO">GO</option>
                        <option value="MA">MA</option><option value="MT">MT</option><option value="MS">MS</option>
                        <option value="MG">MG</option><option value="PA">PA</option><option value="PB">PB</option>
                        <option value="PR">PR</option><option value="PE">PE</option><option value="PI">PI</option>
                        <option value="RJ">RJ</option><option value="RN">RN</option><option value="RS">RS</option>
                        <option value="RO">RO</option><option value="RR">RR</option><option value="SC">SC</option>
                        <option value="SP">SP</option><option value="SE">SE</option><option value="TO">TO</option>
                    </select>
                </div>
            </div>
            <!-- Linha 4: Observações full width -->
            <div style="margin-bottom:20px;">
                <label style="display:block;margin-bottom:5px;font-weight:600;color:#333;">Observações</label>
                <textarea name="notes" class="form-control" rows="2" placeholder="Anotações sobre o cliente..."></textarea>
            </div>
            <div id="quickCustomerError" style="display:none;color:#e74c3c;margin-bottom:10px;"></div>
            <div style="text-align:right;margin-top:16px;display:flex;gap:10px;justify-content:flex-end;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar</button>
                <button type="button" class="btn btn-danger" onclick="closeModal('quickCustomerModal')"><i class="fas fa-times"></i> Cancelar</button>
            </div>
        </form>
    </div>
</div>

<?php if ($printOsData): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Limpa o parâmetro da URL sem recarregar
    if (window.history && window.history.replaceState) {
        window.history.replaceState({}, '', 'index.php');
    }
    showConfirm(
        'Deseja imprimir a Ordem de Serviço <strong>#<?= $printOsData['id'] ?></strong> agora?',
        'Imprimir OS?',
        '<i class="fas fa-print"></i> Imprimir',
        'Agora não',
        'info'
    ).then(function(ok) {
        if (ok) {
            printServiceOrder(<?= json_encode($printOsData) ?>);
        }
    });
});
</script>
<?php endif; ?>

</body>
</html>
