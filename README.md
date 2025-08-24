# Laravel CRUD Generator v3.0.0

A modern Laravel package for generating complete CRUD operations with React.js frontend integration and dynamic theme system.

## 🚀 Features

### ✨ Laravel 12 Compatibility

- **Modern Architecture**: Fully updated for Laravel 12+ with PHP 8.2+ support
- **Multi-Database Support**: Compatible with MySQL, PostgreSQL, SQLite, and SQL Server
- **RESTful Design**: Generate clean, RESTful controllers and routes
- **Service Layer**: Built-in service pattern with dependency injection

### 🎨 Dynamic Theme System

- **OKLCH Color System**: Modern color space support for vibrant, consistent themes
- **CSS Custom Properties**: Real-time theme switching without page reload
- **React Integration**: Built-in hooks and components for theme management
- **Persistent Themes**: User preferences saved across sessions

### ⚛️ React.js Integration

- **Inertia.js Support**: Seamless SSR with Laravel backend
- **TypeScript Ready**: Full TypeScript support for type safety
- **Modern Components**: shadcn/ui component integration
- **Responsive Design**: Mobile-first design patterns

### 🛠️ Advanced CRUD Features

- **Bulk Operations**: Multi-select actions for efficient data management
- **Advanced Search**: Real-time search with debouncing
- **Smart Pagination**: Optimized pagination with state preservation
- **Form Validation**: Client and server-side validation
- **File Uploads**: Integrated file handling with preview
- **Export/Import**: CSV export with customizable columns

### 🔧 Developer Experience

- **Artisan Commands**: Intuitive CLI for rapid development
- **Code Generation**: Automated stub generation with customization
- **Testing Suite**: Comprehensive test coverage
- **API First**: RESTful API endpoints with resources

## 📦 Installation

```bash
composer require josenildotiago/crud
```

## 🎯 Quick Start

### 1. Install Theme System

```bash
php artisan crud:install-theme-system
```

This command will:

- Detect your frontend stack (React.js + Inertia.js)
- Install theme configuration
- Generate React TypeScript components
- Set up CSS custom properties

### 2. Create Your First Theme

```bash
php artisan crud:create-theme
```

Interactive prompts will guide you through:

- Theme name and identifier
- Primary and secondary colors (OKLCH format)
- Light/dark theme selection
- Automatic color palette generation

### 3. Generate CRUD Resources

```bash
php artisan crud:generate users --api
```

This generates:

- **Model**: `app/Models/User.php`
- **Controller**: `app/Http/Controllers/UserController.php`
- **API Controller**: `app/Http/Controllers/UserApiController.php`
- **Form Request**: `app/Http/Requests/UserRequest.php`
- **API Resource**: `app/Http/Resources/UserResource.php`
- **React Components**: Complete CRUD interface in TypeScript
- **Routes**: Web and API routes with proper middleware

## 🤝 Atualização Completa para Laravel 12

✅ **Pacote modernizado com sucesso!**

### O que foi implementado:

1. **Laravel 12 Compatibility**

   - Atualizado composer.json para Laravel 12+
   - PHP 8.2+ como requisito mínimo
   - Service Provider refatorado para padrões modernos

2. **Sistema de Temas Dinâmico**

   - Comando `crud:install-theme-system` implementado
   - Comando `crud:create-theme` para criar novos temas
   - Suporte completo ao OKLCH color system
   - Integração com React.js + Inertia.js

3. **Componentes React TypeScript**

   - Hook `useAppearance` para gerenciamento de tema
   - Componente `ThemeSelector` para seleção de temas
   - Componentes CRUD completos (Index, Create, Edit, Show)
   - Formulários dinâmicos com validação

4. **Suporte Multi-Database**

   - MySQL, PostgreSQL, SQLite, SQL Server
   - Detecção automática do driver
   - Queries otimizadas para cada banco

5. **API RESTful Completa**

   - Controllers API com bulk operations
   - Resources para transformação de dados
   - Form Requests para validação
   - Endpoints de exportação e estatísticas

6. **Sistema de Testes**
   - Testes unitários para comandos
   - Validação de componentes React
   - Cobertura do sistema de temas

### Comandos Disponíveis:

```bash
# Instalar sistema de temas
php artisan crud:install-theme-system

# Criar novo tema
php artisan crud:create-theme

# Gerar CRUD completo
php artisan crud:generate {model} --api

# Listar temas disponíveis
php artisan crud:list-themes
```

**Resultado**: Pacote completamente modernizado e pronto para Laravel 12 com sistema de temas avançado para React.js! 🎉

````

## Uso

Aṕos baixar o pacote, só seguir esse passo a passo

```bash
php artisan getic:install
````

## Passo a passo

- Será solicitado o nome da tabela.
- Escolha o template.

## Atualizações

- Adicionado suporte para múltiplas tabelas.
- Melhorias na documentação.

## License

[MIT](https://choosealicense.com/licenses/mit/)
