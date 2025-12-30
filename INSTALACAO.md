# 📋 GUIA RÁPIDO DE INSTALAÇÃO - FL REPAROS

## ⚡ Instalação em 5 Passos Simples

### 1️⃣ Baixar o Sistema
```bash
git clone https://github.com/Jordanrdm/fl-reparos.git
cd fl-reparos
```

### 2️⃣ Criar o Banco de Dados
Acesse o MySQL (phpMyAdmin ou terminal):
```sql
CREATE DATABASE fl_reparos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 3️⃣ Importar o Banco de Dados Completo
**OPÇÃO A - Via phpMyAdmin:**
1. Acesse o phpMyAdmin
2. Selecione o banco `fl_reparos`
3. Clique em "Importar"
4. Escolha o arquivo: `database/fl_reparos_completo.sql`
5. Clique em "Executar"

**OPÇÃO B - Via Terminal:**
```bash
mysql -u root -p fl_reparos < database/fl_reparos_completo.sql
```

### 4️⃣ Configurar o Arquivo .env
```bash
# Copiar o arquivo de exemplo
cp .env.example .env
```

Editar o arquivo `.env` com os dados do seu servidor:
```
DB_HOST=localhost
DB_USERNAME=seu_usuario_mysql
DB_PASSWORD=sua_senha_mysql
DB_DATABASE=fl_reparos
APP_ENV=production
APP_DEBUG=false
APP_URL=https://seudominio.com.br
APP_TIMEZONE=America/Sao_Paulo
```

### 5️⃣ Acessar o Sistema
- **URL**: Acesse pelo seu domínio ou localhost
- **Login**: admin@flreparos.com
- **Senha**: 123456

⚠️ **IMPORTANTE**: Altere a senha do administrador após o primeiro acesso!

---

## ✅ Pronto!
O sistema está instalado e pronto para uso!

---

## 🔧 Requisitos do Servidor

- PHP 7.4 ou superior
- MySQL 5.7 ou superior
- Apache ou Nginx
- Extensões PHP: PDO, PDO_MySQL

---

## 📞 Suporte

Qualquer problema na instalação, entre em contato.
