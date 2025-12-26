<?php
/**
 * FL REPAROS - Footer do Sistema  
 * Máximo: 50 linhas
 */
?>
    </main>

    <!-- Footer -->
    <footer style="
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
        text-align: center;
        padding: 20px;
        margin-top: 50px;
        color: rgba(255, 255, 255, 0.8);
    ">
        <p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?> - Sistema de Gestão v<?php echo APP_VERSION; ?></p>
    </footer>

    <!-- JavaScript Principal -->
    <script src="<?php echo APP_URL; ?>/assets/js/main.js"></script>
    
    <!-- JavaScript específico da página -->
    <?php if (isset($pageJS)): ?>
        <script src="<?php echo APP_URL; ?>/assets/js/<?php echo $pageJS; ?>.js"></script>
    <?php endif; ?>
    
    <!-- Atalhos de Teclado -->
    <script>
        document.addEventListener('keydown', function(e) {
            // Só funciona se não estiver digitando em input
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
            
            switch(e.key) {
                case 'F2':
                    e.preventDefault();
                    window.location.href = '<?php echo APP_URL; ?>/modules/products/';
                    break;
                case 'F3':
                    e.preventDefault();
                    window.location.href = '<?php echo APP_URL; ?>/modules/pdv/';
                    break;
                case 'F4':
                    e.preventDefault();
                    window.location.href = '<?php echo APP_URL; ?>/modules/cashflow/';
                    break;
                case 'F5':
                    e.preventDefault();
                    window.location.href = '<?php echo APP_URL; ?>/modules/service-orders/';
                    break;
            }
        });
    </script>
</body>
</html>