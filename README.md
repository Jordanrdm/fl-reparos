# FL REPAROS - Sistema de Gestão para Assistência Técnica

Sistema completo de gestão para assistências técnicas de celulares e eletrônicos.

## 📋 Funcionalidades

- **PDV (Ponto de Venda)**: Sistema completo de vendas com múltiplas formas de pagamento
- **Ordem de Serviço**: Controle completo de reparos com checklist de equipamentos
- **Produtos**: Gestão de estoque, códigos de barra e categorias
- **Clientes**: Cadastro e histórico de clientes
- **Contas a Receber**: Controle financeiro de recebimentos
- **Despesas**: Registro e controle de gastos
- **Fluxo de Caixa**: Controle de entradas e saídas
- **Relatórios**: Análises e métricas do negócio
- **Usuários**: Gerenciamento com 3 níveis de permissão (Admin, Gerente, Vendedor)

## 🚀 Instalação

### Requisitos

- PHP 7.4 ou superior
- MySQL 5.7 ou superior
- Apache/Nginx
- Extensões PHP: PDO, PDO_MySQL

### Passos

1. **Clone o repositório**
   ```bash
   git clone [url-do-repositorio]
   cd flreparos
   ```

2. **Configure o banco de dados**
   ```bash
   # Crie o banco de dados
   mysql -u root -p
   CREATE DATABASE fl_reparos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   EXIT;
   ```

3. **Execute as migrations**
   ```bash
   # Execute todos os arquivos SQL em database/migrations/ na seguinte ordem:
   mysql -u root -p fl_reparos < database/migrations/update_users_table_roles.sql
   mysql -u root -p fl_reparos < database/migrations/fix_users_roles.sql
   mysql -u root -p fl_reparos < database/migrations/check_and_fix_users_table.sql
   mysql -u root -p fl_reparos < database/migrations/fix_users_role_enum.sql
   mysql -u root -p fl_reparos < database/migrations/create_accounts_receivable_table.sql
   mysql -u root -p fl_reparos < database/migrations/create_cash_register_table.sql
   mysql -u root -p fl_reparos < database/migrations/add_allow_price_edit_to_products.sql
   mysql -u root -p fl_reparos < database/migrations/refactor_service_orders_table.sql
   ```

4. **Configure as variáveis de ambiente**
   ```bash
   cp .env.example .env
   # Edite o arquivo .env com suas credenciais
   ```

5. **Ajuste as permissões**
   ```bash
   chmod 755 -R .
   chmod 644 .env
   ```

6. **Acesse o sistema**
   - URL: `http://seudominio.com.br`
   - Email padrão: `admin@flreparos.com`
   - Senha padrão: `123456`

   **IMPORTANTE**: Altere a senha do admin após o primeiro login!

## 🔐 Segurança

- Senhas hasheadas com bcrypt
- Proteção CSRF em formulários
- Prepared statements (proteção SQL Injection)
- Validação e sanitização de inputs
- Controle de permissões por role (RBAC)
- Session timeout configurável

## 👥 Perfis de Usuário

### Administrador
- Acesso total ao sistema
- Gerenciamento de usuários
- Todas as operações CRUD

### Gerente
- Acesso a PDV, OS, Produtos, Clientes
- Contas a Receber, Fluxo de Caixa, Relatórios
- Não pode gerenciar usuários
- Não pode deletar registros

### Vendedor
- Acesso apenas ao PDV (vendas completas)
- Visualização de OS, Produtos e Clientes
- Sem acesso a relatórios financeiros

## 📁 Estrutura de Diretórios

```
flreparos/
├── assets/          # CSS, JS, imagens
├── config/          # Configurações (database, permissions, app)
├── database/        # Migrations SQL
├── includes/        # Componentes reutilizáveis (header, sidebar)
├── modules/         # Módulos do sistema
│   ├── pdv/
│   ├── service_orders/
│   ├── products/
│   ├── customers/
│   ├── accounts_receivable/
│   ├── expenses/
│   ├── cashflow/
│   ├── reports/
│   └── users/
├── .env             # Configurações sensíveis (não versionar)
├── .env.example     # Template de configuração
├── index.php        # Dashboard
└── login.php        # Autenticação
```

## 🛠️ Tecnologias

- **Backend**: PHP 7.4+
- **Banco de Dados**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, JavaScript (Vanilla)
- **Ícones**: Font Awesome 6.0
- **Padrão**: MVC simplificado

## 📝 Notas de Desenvolvimento

- O sistema usa PDO para conexão com banco de dados
- Todas as senhas são hasheadas com `password_hash()` (bcrypt)
- Sistema de permissões baseado em roles está em `config/permissions.php`
- Validações de desconto acima de 5% requerem senha de gerente/admin no PDV

## 🐛 Troubleshooting

### Erro de conexão com banco de dados
- Verifique as credenciais no arquivo `.env`
- Certifique-se que o MySQL está rodando
- Verifique se o banco `fl_reparos` existe

### Página em branco após login
- Verifique se todas as migrations foram executadas
- Confira os logs de erro do PHP
- Verifique permissões de arquivos

### Desconto no PDV não funciona
- Certifique-se que o usuário tem role 'admin' ou 'manager'
- Use o email cadastrado, não username
- Verifique se o usuário está ativo (status='active')

## 📄 Licença

Propriedade de FL REPAROS. Todos os direitos reservados.

## 👨‍💻 Suporte

Para suporte técnico, entre em contato com o desenvolvedor.
