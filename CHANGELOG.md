# Changelog

## [3.0.18] - 2025-08-26

### 🎉 Major Release - React.js shadcn/ui Integration

### ✨ Adicionado

- **FormFieldReact.stub**: Novo template específico para React com shadcn/ui
- **Card Layout System**: Create.stub completamente redesenhado com Card components
- **Smart Placeholders**: Sistema inteligente de placeholders baseados no nome dos campos
- **Enhanced Form Generation**: Método `generateFormFields()` para criação automática de campos
- **AppLayout Integration**: Migração completa do AuthenticatedLayout para AppLayout (Laravel 12)
- **Breadcrumbs System**: Sistema de navegação hierárquica em todos os componentes React

### 🔧 Melhorado

- **Create.stub**: Layout moderno com CardHeader, CardContent, CardFooter
- **Controller Field Mapping**: Método `getControllerFieldsWithModel()` com resolução correta de variáveis
- **JavaScript Form Fields**: Geração aprimorada de objetos useForm para Create e Edit
- **TypeScript Integration**: Interfaces automáticas baseadas nas colunas da tabela
- **Error Handling**: Exibição de erros integrada aos campos de formulário
- **Loading States**: LoaderCircle component durante submissão de formulários

### 🎨 Interface Modernizada

- **shadcn/ui Components**: Integração completa com Button, Card, Input, Label
- **Grid Responsivo**: Layout responsivo sm:grid-cols-12 para todos os formulários
- **Design Consistency**: Padrão visual consistente em todos os componentes
- **Mobile-First**: Design responsivo otimizado para dispositivos móveis

### 🐛 Correções Críticas

- **Variable Substitution**: Corrigida substituição de `{{modelNameLowerCase}}` em controllerFields
- **Handlebars Syntax**: Removida sintaxe Handlebars incompatível, substituída por PHP str_replace
- **Database Column Processing**: Corrigido erro TypeError em getFilteredColumns()
- **Command Namespace**: Padronizado namespace 'crud:' para todos os comandos

### 🚀 Performance

- **Stub Caching**: Templates carregados apenas quando necessários
- **Batch Generation**: Múltiplos arquivos gerados em uma única operação
- **Optimized Queries**: Queries de banco otimizadas com cache de colunas

---

## [3.0.17] - 2025-08-26

### 🔧 Bug Fixes

- **FormField Template**: Corrigido template de campo para React
- **Method Resolution**: Resolvidos conflitos de métodos abstratos

---

## [3.0.16] - 2025-08-26

### 🔧 Maintenance

- **Code Cleanup**: Limpeza de código e otimizações menores
- **Documentation**: Atualização de comentários e documentação

---

## [3.0.15] - 2025-08-26

### 🔧 Bug Fixes

- **Stub Path Resolution**: Corrigido caminho de resolução dos stubs
- **Command Signature**: Ajustada assinatura dos comandos

---

## [3.0.14] - 2025-08-26

### 🔧 Bug Fixes

- **Template Variables**: Corrigidas variáveis de template nos stubs React
- **Form Generation**: Melhorada geração de formulários

---

## [3.0.13] - 2025-08-26

### ✨ Adicionado

- **Enhanced React Templates**: Templates React aprimorados
- **Better Error Handling**: Melhor tratamento de erros

---

## [3.0.12] - 2025-08-26

### ✨ Adicionado

- **FormFieldReact.stub**: Template inicial para campos React
- **Card Layout**: Primeira implementação do layout com Cards
- **Smart Placeholders**: Placeholders básicos para campos

---

## [3.0.11] - 2025-08-26

### 🐛 Bug Fixes

- **Controller Field Variable Fix**: Corrigida substituição de variáveis nos campos do controller
- **Template Processing**: Melhorada processamento de templates

---

## [3.0.10] - 2025-08-26

### 🐛 Bug Fixes

- **Controller Field Mapping**: Corrigido mapeamento de campos no controller
- **Variable Scope**: Corrigido escopo de variáveis em templates

---

## [3.0.9] - 2025-08-26

### 🐛 Bug Fixes

- **InertiaController.stub**: Substituída sintaxe {{#each columns}} por {{controllerFields}}
- **Dynamic Field Generation**: Adicionada geração dinâmica de campos para método index do controller
- **Database Table Reference**: Corrigido {{modelTable}} para usar nome real da tabela

### 🔧 New Controller Features

- `{{controllerFields}}` - Mapeamentos dinâmicos de campos para método index
- Resolução correta de nome da tabela do banco
- Processamento aprimorado de campos para respostas Inertia

---

## [3.0.8] - 2025-08-26

### 🛠️ Major Template Fixes

- **Fixed Handlebars Syntax**: Substituída sintaxe {{#each}} por str_replace() do PHP
- **Dynamic Field Generation**: Todos os stubs React agora usam substituição correta de variáveis
- **TypeScript Support**: Adicionada geração de campos de interface TypeScript
- **Table Components**: Corrigida geração de células de tabela para componente Index
- **Show Fields**: Simplificado e corrigido exibição de campos no componente Show

### 🔧 Enhanced Replacement Variables

- `{{fillableColumns}}` - Campos de objeto JavaScript para formulários Create
- `{{editFillableColumns}}` - Campos de objeto JavaScript para formulários Edit com dados do modelo
- `{{typeScriptColumns}}` - Definições de campos de interface TypeScript
- `{{tableCells}}` - Geração de células de tabela para componente Index
- `{{showFieldsReact}}` - Campos de exibição para componente Show

---

## [3.0.7] - 2025-08-26

### 🐛 Critical Bug Fix

- **Fixed getFilteredColumns() Error**: Resolvido TypeError ao processar colunas do banco
- **Database Column Processing**: Corrigido erro explode() em objetos stdClass
- **Command Compatibility**: Agora usa método getFilteredColumns() da classe pai corretamente

---

## [3.0.6] - 2025-08-26

### 🎉 Laravel 12 Modernization Complete

#### ✨ New Features

- **React Components**: Compatibilidade completa com Laravel 12
- **AppLayout Integration**: Atualizado de AuthenticatedLayout para AppLayout
- **Breadcrumbs System**: Adicionada navegação breadcrumb abrangente
- **Enhanced Form Handling**: Detecção inteligente de campos fillable para useForm
- **Route Organization**: Arquivos de rota separados por modelo com middleware adequado

#### 🔧 Technical Improvements

- **fillableColumns Support**: Geração dinâmica de campos para formulários React
- **Enhanced buildReplacements**: Adicionadas variáveis fillableColumns, modelRoutePlural e outras
- **Filtered Column Generation**: Exclui timestamps e campos de sistema dos formulários
- **JavaScript Form Fields**: Geração adequada de objetos useForm
- **ModelRoutes.stub**: Template para organização de arquivos de rota separados

---

## [3.0.3] - 2025-08-26

### 🔧 Command Namespace Fix

- **Fixed command namespace**: De `themes:` para `crud:`
- **Resolved "crud namespace not found" error**: Comandos agora funcionam corretamente

---

## [3.0.2] - 2025-08-26

### 🔧 Missing Stub Files

- **Added missing ApiResourceCollection.stub**: Arquivo stub ausente adicionado
- **Fixed Form.stub references**: Corrigidas referências a arquivos inexistentes

---

## [3.0.1] - 2025-08-26

### 🔧 Installation Fix

- **Fixed abstract method implementations**: InstallCommand agora implementa todos os métodos abstratos
- **Resolved installation blocking errors**: Erros que impediam instalação foram corrigidos

---

## [3.0.0] - 2025-08-26

### 🎉 Initial Laravel 12 Release

- **Base Laravel 12 compatibility**: Compatibilidade base com Laravel 12
- **React.js integration with Inertia.js**: Integração React.js com Inertia.js
- **Modern CRUD generator with themes**: Gerador CRUD moderno com sistema de temas
- **Dynamic theme system**: Sistema de temas dinâmicos
- **TypeScript support**: Suporte completo ao TypeScript
- **API RESTful generation**: Geração automática de APIs RESTful
