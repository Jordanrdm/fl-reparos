<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Produtos - FL REPAROS</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f0f0f0; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; }
        .btn { background: #2196F3; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎉 MÓDULO PRODUTOS FUNCIONANDO!</h1>
        <p>✅ F2 está funcionando corretamente</p>
        <p>✅ Redirecionamento OK</p>
        <p>✅ Sessão válida: Usuário <?php echo $_SESSION['user_id']; ?></p>
        
        <hr>
        <h3>🧪 Teste realizado com sucesso!</h3>
        <p>Agora podemos implementar o módulo completo.</p>
        
        <br>
        <a href="../../index.php" class="btn">← Voltar ao Dashboard</a>
    </div>
    
    <script>
        console.log('✅ Página produtos carregada com sucesso!');
        console.log('🔗 URL:', window.location.href);
    </script>
</body>
</html>