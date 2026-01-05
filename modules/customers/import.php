<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

require_once('../../config/database.php');
require_once('SimpleExcelReader.php');

$conn = $database->getConnection();

$message = '';
$messageType = '';

// Processar upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file'];

    // Validar arquivo
    $allowedExtensions = ['csv', 'xlsx', 'xls'];
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($fileExtension, $allowedExtensions)) {
        $message = 'Formato de arquivo inválido! Use CSV, XLS ou XLSX.';
        $messageType = 'error';
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $message = 'Erro no upload do arquivo!';
        $messageType = 'error';
    } else {
        try {
            $importedCount = 0;
            $errorCount = 0;
            $errors = [];

            // Ler arquivo usando SimpleExcelReader (passando nome original)
            $data = SimpleExcelReader::readFile($file['tmp_name'], $file['name']);

            if (empty($data)) {
                $message = 'Arquivo vazio ou sem dados.';
                $messageType = 'error';
            } else {
                // Primeira linha é o cabeçalho - detectar colunas automaticamente
                $cabecalho = array_shift($data);
                $mapeamento = SimpleExcelReader::detectarColunas($cabecalho);

                // Verificar se pelo menos uma coluna de nome foi detectada
                if (!in_array('name', $mapeamento)) {
                    $message = 'Não foi possível detectar a coluna de NOME/CLIENTE na planilha. Certifique-se de que existe uma coluna com nome do cliente.';
                    $messageType = 'error';
                } else {
                    // Processar linhas
                    $line = 1;
                    foreach ($data as $row) {
                        $line++;

                        // Mapear dados da linha para os campos do banco
                        $dadosCliente = [
                            'name' => null,
                            'cpf_cnpj' => null,
                            'phone' => null,
                            'email' => null,
                            'address' => null,
                            'city' => null,
                            'state' => null,
                            'zipcode' => null,
                            'notes' => null
                        ];

                        foreach ($mapeamento as $indiceColuna => $campoBanco) {
                            if (isset($row[$indiceColuna]) && !empty(trim($row[$indiceColuna]))) {
                                $dadosCliente[$campoBanco] = trim($row[$indiceColuna]);
                            }
                        }

                        // Validar se tem pelo menos o nome
                        if (empty($dadosCliente['name'])) {
                            $errors[] = "Linha $line: Nome é obrigatório";
                            $errorCount++;
                            continue;
                        }

                        try {
                            $stmt = $conn->prepare("INSERT INTO customers
                                (name, cpf_cnpj, phone, email, address, city, state, zipcode, notes, created_at, updated_at)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");

                            $stmt->execute([
                                $dadosCliente['name'],
                                $dadosCliente['cpf_cnpj'],
                                $dadosCliente['phone'],
                                $dadosCliente['email'],
                                $dadosCliente['address'],
                                $dadosCliente['city'],
                                $dadosCliente['state'],
                                $dadosCliente['zipcode'],
                                $dadosCliente['notes']
                            ]);

                            $importedCount++;
                        } catch (PDOException $e) {
                            $errors[] = "Linha $line: " . $e->getMessage();
                            $errorCount++;
                        }
                    }
                }
            }

            if ($importedCount > 0) {
                $message = "✅ $importedCount cliente(s) importado(s) com sucesso!";
                if ($errorCount > 0) {
                    $message .= " ($errorCount erro(s))";
                }
                $messageType = 'success';
            } elseif ($errorCount > 0 && $importedCount === 0) {
                $message = "❌ Nenhum cliente importado. $errorCount erro(s) encontrado(s).";
                $messageType = 'error';
            }

        } catch (Exception $e) {
            $message = 'Erro ao processar arquivo: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar Clientes - FL REPAROS</title>
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
            max-width: 800px;
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

        .upload-area {
            border: 3px dashed #667eea;
            border-radius: 15px;
            padding: 40px;
            text-align: center;
            background: #f8f9ff;
            margin-bottom: 30px;
            transition: all 0.3s;
        }

        .upload-area:hover {
            border-color: #764ba2;
            background: #f0f2ff;
        }

        .upload-icon {
            font-size: 60px;
            color: #667eea;
            margin-bottom: 20px;
        }

        input[type="file"] {
            display: none;
        }

        .btn-upload {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-upload:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .btn-back {
            background: #6c757d;
            color: white;
            text-decoration: none;
            padding: 12px 25px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
        }

        .btn-back:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .instructions {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .instructions h3 {
            color: #856404;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .instructions ol {
            margin-left: 20px;
            color: #856404;
        }

        .instructions li {
            margin-bottom: 8px;
        }

        .file-name {
            margin-top: 15px;
            font-weight: 600;
            color: #667eea;
        }

        .example-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 14px;
        }

        .example-table th,
        .example-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        .example-table th {
            background: #667eea;
            color: white;
        }

        .btn-download {
            background: #28a745;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 15px;
            transition: all 0.3s;
        }

        .btn-download:hover {
            background: #218838;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>
            <i class="fas fa-file-excel"></i>
            Importar Clientes
        </h1>
        <p class="subtitle">Importe seus clientes através de uma planilha CSV</p>

        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>">
                <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
                <?= $message ?>
            </div>
        <?php endif; ?>

        <div class="instructions">
            <h3><i class="fas fa-info-circle"></i> Formato do Arquivo CSV</h3>
            <ol>
                <li>O arquivo deve estar no formato <strong>CSV</strong> (separado por vírgulas)</li>
                <li>A primeira linha deve conter os cabeçalhos (será ignorada)</li>
                <li>As colunas devem estar nesta ordem:</li>
            </ol>

            <table class="example-table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>CPF/CNPJ</th>
                        <th>Telefone</th>
                        <th>Email</th>
                        <th>Endereço</th>
                        <th>Cidade</th>
                        <th>Estado</th>
                        <th>CEP</th>
                        <th>Observações</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>João Silva</td>
                        <td>123.456.789-00</td>
                        <td>(11) 98765-4321</td>
                        <td>joao@email.com</td>
                        <td>Rua A, 123</td>
                        <td>São Paulo</td>
                        <td>SP</td>
                        <td>01234-567</td>
                        <td>Cliente VIP</td>
                    </tr>
                </tbody>
            </table>

            <a href="modelo_importacao.csv" class="btn-download" download>
                <i class="fas fa-download"></i>
                Baixar Modelo CSV
            </a>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <div class="upload-area" onclick="document.getElementById('fileInput').click()">
                <div class="upload-icon">
                    <i class="fas fa-cloud-upload-alt"></i>
                </div>
                <h3>Clique aqui para selecionar o arquivo</h3>
                <p>Formatos aceitos: CSV, XLS, XLSX</p>
                <input type="file" id="fileInput" name="excel_file" accept=".csv,.xlsx,.xls" onchange="showFileName(this)">
                <div class="file-name" id="fileName"></div>
            </div>

            <div style="display: flex; gap: 15px; justify-content: center;">
                <button type="submit" class="btn-upload">
                    <i class="fas fa-upload"></i>
                    Importar Clientes
                </button>
                <a href="index.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i>
                    Voltar
                </a>
            </div>
        </form>
    </div>

    <script>
        function showFileName(input) {
            const fileName = input.files[0]?.name;
            const fileNameDiv = document.getElementById('fileName');
            if (fileName) {
                fileNameDiv.innerHTML = `<i class="fas fa-file"></i> ${fileName}`;
            }
        }
    </script>
</body>
</html>
