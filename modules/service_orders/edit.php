<?php
include('../../config/database.php');
include('../../includes/header.php');

// Verifica se o ID foi informado
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<script>alert('ID da Ordem de Serviço não informado.'); window.location.href='index.php';</script>";
    exit;
}

$id = $_GET['id'];

// Buscar a OS pelo ID
$stmt = $conn->prepare("SELECT * FROM service_orders WHERE id = ?");
$stmt->execute([$id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    echo "<script>alert('Ordem de Serviço não encontrada.'); window.location.href='index.php';</script>";
    exit;
}

// Buscar lista de clientes para o select
$stmt_clients = $conn->prepare("SELECT id, name FROM customers ORDER BY name ASC");
$stmt_clients->execute();
$customers = $stmt_clients->fetchAll(PDO::FETCH_ASSOC);

// Atualizar OS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id = $_POST['customer_id'];
    $device = $_POST['device'];
    $problem = $_POST['problem'];
    $diagnosis = $_POST['diagnosis'];
    $solution = $_POST['solution'];
    $total_cost = $_POST['total_cost'];
    $status = $_POST['status'];
    $delivery_date = ($_POST['status'] === 'delivered') ? date('Y-m-d H:i:s') : null;

    $stmt_update = $conn->prepare("UPDATE service_orders 
        SET customer_id=?, device=?, problem=?, diagnosis=?, solution=?, total_cost=?, status=?, delivery_date=?
        WHERE id=?");

    $updated = $stmt_update->execute([
        $customer_id, $device, $problem, $diagnosis, $solution, $total_cost, $status, $delivery_date, $id
    ]);

    if ($updated) {
        echo "<script>
                alert('Ordem de Serviço atualizada com sucesso!');
                window.location.href='index.php';
              </script>";
        exit;
    } else {
        echo "<script>alert('Erro ao atualizar OS.');</script>";
    }
}
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <a href="index.php" class="btn btn-secondary me-2">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
            <h4 class="d-inline"><i class="fas fa-edit"></i> Editar Ordem de Serviço #<?= htmlspecialchars($order['id']) ?></h4>
        </div>
    </div>

    <div class="card shadow-sm rounded-3">
        <div class="card-body">
            <form method="POST">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Cliente</label>
                        <select name="customer_id" class="form-control" required>
                            <option value="">Selecione o cliente</option>
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?= $customer['id'] ?>" 
                                    <?= ($customer['id'] == $order['customer_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($customer['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Dispositivo</label>
                        <input type="text" name="device" value="<?= htmlspecialchars($order['device']) ?>" class="form-control" required>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Problema</label>
                        <textarea name="problem" class="form-control" required><?= htmlspecialchars($order['problem']) ?></textarea>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Diagnóstico</label>
                        <textarea name="diagnosis" class="form-control"><?= htmlspecialchars($order['diagnosis']) ?></textarea>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Solução</label>
                        <textarea name="solution" class="form-control"><?= htmlspecialchars($order['solution']) ?></textarea>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Valor Total (R$)</label>
                        <input type="number" step="0.01" name="total_cost" value="<?= htmlspecialchars($order['total_cost']) ?>" class="form-control">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <?php
                            $statuses = [
                                'open' => 'Aberta',
                                'in_progress' => 'Em andamento',
                                'completed' => 'Concluída',
                                'delivered' => 'Entregue',
                                'cancelled' => 'Cancelada'
                            ];
                            foreach ($statuses as $value => $label): ?>
                                <option value="<?= $value ?>" <?= ($order['status'] == $value) ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Atualizar OS
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include('../../includes/footer.php'); ?>
