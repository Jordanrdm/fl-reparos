<?php
include('../../config/database.php');
include('../../includes/header.php');

// Buscar lista de clientes para o select
$stmt = $conn->prepare("SELECT id, name FROM customers ORDER BY name ASC");
$stmt->execute();
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Verifica se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id = $_POST['customer_id'];
    $device = $_POST['device'];
    $problem = $_POST['problem'];
    $diagnosis = $_POST['diagnosis'];
    $solution = $_POST['solution'];
    $total_cost = $_POST['total_cost'];
    $status = $_POST['status'];

    $user_id = 1; // por enquanto fixo (usuário logado)
    $entry_date = date('Y-m-d H:i:s');

    $stmt = $conn->prepare("INSERT INTO service_orders 
        (customer_id, user_id, device, problem, diagnosis, solution, total_cost, status, entry_date)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $success = $stmt->execute([
        $customer_id, $user_id, $device, $problem, $diagnosis, $solution, $total_cost, $status, $entry_date
    ]);

    if ($success) {
        echo "<script>
                alert('Ordem de serviço cadastrada com sucesso!');
                window.location.href='index.php';
              </script>";
        exit;
    } else {
        echo "<script>alert('Erro ao cadastrar ordem de serviço.');</script>";
    }
}
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4><i class="fas fa-plus-circle"></i> Nova Ordem de Serviço</h4>
        <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
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
                                <option value="<?= $customer['id'] ?>"><?= htmlspecialchars($customer['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Dispositivo</label>
                        <input type="text" name="device" class="form-control" required placeholder="Ex: iPhone 12, Samsung A30...">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Problema</label>
                        <textarea name="problem" class="form-control" required placeholder="Descreva o problema..."></textarea>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Diagnóstico</label>
                        <textarea name="diagnosis" class="form-control" placeholder="Diagnóstico técnico (opcional)..."></textarea>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Solução</label>
                        <textarea name="solution" class="form-control" placeholder="Descreva a solução (opcional)..."></textarea>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Valor Total (R$)</label>
                        <input type="number" step="0.01" name="total_cost" class="form-control" placeholder="0,00">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <option value="open">Aberta</option>
                            <option value="in_progress">Em andamento</option>
                            <option value="completed">Concluída</option>
                            <option value="delivered">Entregue</option>
                            <option value="cancelled">Cancelada</option>
                        </select>
                    </div>
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include('../../includes/footer.php'); ?>
