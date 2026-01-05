<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$configFile = '../../config/warranty_terms.php';
$warrantyConfig = require($configFile);

$message = '';
$messageType = '';

// Processar salvamento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    try {
        $newConfig = [
            'title' => $_POST['title'] ?? 'TERMOS DE GARANTIA',
            'clauses' => [],
            'footer' => $_POST['footer'] ?? ''
        ];

        // Processar cláusulas
        for ($i = 1; $i <= 4; $i++) {
            if (isset($_POST["clause_{$i}_text"])) {
                $newConfig['clauses'][] = [
                    'number' => (string)$i,
                    'text' => $_POST["clause_{$i}_text"]
                ];
            }
        }

        // Gerar conteúdo do arquivo PHP
        $phpContent = "<?php\n";
        $phpContent .= "/**\n";
        $phpContent .= " * Configuração dos Termos de Garantia\n";
        $phpContent .= " *\n";
        $phpContent .= " * Este arquivo contém o texto padrão que será exibido na impressão das Ordens de Serviço.\n";
        $phpContent .= " * Editado em: " . date('d/m/Y H:i:s') . "\n";
        $phpContent .= " */\n\n";
        $phpContent .= "return " . var_export($newConfig, true) . ";\n";

        // Salvar arquivo
        if (file_put_contents($configFile, $phpContent)) {
            $message = '✅ Termos de garantia salvos com sucesso!';
            $messageType = 'success';
            $warrantyConfig = $newConfig;
        } else {
            $message = '❌ Erro ao salvar arquivo de configuração.';
            $messageType = 'error';
        }
    } catch (Exception $e) {
        $message = '❌ Erro: ' . $e->getMessage();
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurar Garantia - FL REPAROS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        h1 {
            color: #667eea;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .form-group {
            margin-bottom: 25px;
        }

        label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 14px;
        }

        input[type="text"],
        textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.3s;
        }

        input[type="text"]:focus,
        textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        .clause-box {
            background: #f8f9ff;
            border: 2px solid #667eea;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .clause-header {
            font-size: 16px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .help-text {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
            padding: 12px 15px;
            border-radius: 8px;
            font-size: 13px;
            margin-top: 10px;
        }

        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>
            <i class="fas fa-shield-alt"></i>
            Configurar Termos de Garantia
        </h1>
        <p class="subtitle">Personalize os termos de garantia que serão exibidos nas Ordens de Serviço impressas</p>

        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>">
                <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
                <?= $message ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="action" value="save">

            <div class="form-group">
                <label for="title">
                    <i class="fas fa-heading"></i> Título da Seção de Garantia
                </label>
                <input type="text"
                       id="title"
                       name="title"
                       value="<?= htmlspecialchars($warrantyConfig['title']) ?>"
                       required>
                <div class="help-text">
                    <i class="fas fa-info-circle"></i> Este título aparecerá no topo da seção de garantia
                </div>
            </div>

            <?php foreach ($warrantyConfig['clauses'] as $index => $clause): ?>
            <div class="clause-box">
                <div class="clause-header">
                    <i class="fas fa-file-contract"></i>
                    Cláusula <?= $clause['number'] ?>
                </div>
                <div class="form-group">
                    <label for="clause_<?= $clause['number'] ?>_text">Texto da Cláusula</label>
                    <textarea id="clause_<?= $clause['number'] ?>_text"
                              name="clause_<?= $clause['number'] ?>_text"
                              required><?= htmlspecialchars($clause['text']) ?></textarea>
                    <?php if ($index === 0): ?>
                    <div class="help-text">
                        <i class="fas fa-lightbulb"></i>
                        <strong>Dica:</strong> Use <code>[PERIODO_GARANTIA]</code> onde você quer que apareça o período de garantia
                        (exemplo: 90 dias, 6 meses, etc.)
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="form-group">
                <label for="footer">
                    <i class="fas fa-quote-right"></i> Texto de Rodapé
                </label>
                <textarea id="footer"
                          name="footer"
                          required><?= htmlspecialchars($warrantyConfig['footer']) ?></textarea>
                <div class="help-text">
                    <i class="fas fa-info-circle"></i> Este texto aparecerá ao final da seção de garantia
                </div>
            </div>

            <div class="button-group">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    Salvar Alterações
                </button>
                <a href="../service_orders/index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Voltar
                </a>
            </div>
        </form>
    </div>
</body>
</html>
