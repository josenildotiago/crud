# Changelog

## [3.0.13] - 2025-08-26

### ✨ Adicionado

- **FormFieldReact.stub**: Novo template específico para React com shadcn/ui
- **Card Layout**: Create.stub atualizado com layout Card moderno
- **Smart Placeholders**: Placeholders inteligentes baseados no nome dos campos
- **shadcn/ui Integration**: Uso completo de Button, Card, Input, Label

### 🔧 Melhorado

- **Create.stub**: Layout moderno com Card, CardHeader, CardContent, CardFooter
- **Form Fields**: Geração automática de campos com componentes shadcn/ui
- **Error Handling**: Exibição de erros integrada aos campos
- **Loading States**: LoaderCircle durante submissão de formulários

### 🎨 Interface

- **Design Moderno**: Cards com header, content e footer bem estruturados
- **Grid Responsivo**: Layout responsivo sm:grid-cols-12
- **Componentes UI**: Integração completa com @/components/ui

---

## [3.0.10] - Controller Field Variable Fixhangelog

## [3.0.11] - Controller Field Variable Fix

### 🐛 Bug Fixes

- **Fixed {{controllerFields}} Variable Substitution**: Corrected template variable replacement in controller fields
- **Removed Unnecessary Null Safety**: Removed `?` from `created_at` and `updated_at` formatting
- **Enhanced Field Generation**: Controller fields now properly use {{modelNameLowerCase}} variable

### 🔧 Technical Improvements

- Template variable substitution now works correctly in controller field mappings
- Cleaner date formatting without unnecessary null safety operators
- Proper variable scope handling in field generation methods

---

## [3.0.9] - Controller Template Fix

### 🐛 Bug Fixes

- **Fixed InertiaController.stub**: Replaced {{#each columns}} with {{controllerFields}}
- **Controller Field Mapping**: Added proper dynamic field generation for controller index method
- **Database Table Reference**: Fixed {{modelTable}} to use actual table name instead of class name
- **Enhanced Field Processing**: Controllers now generate proper field mappings for all data types

### 🔧 New Controller Features

- `{{controllerFields}}` - Dynamic field mappings for index method
- Proper database table name resolution
- Enhanced field processing for Inertia responses

---

## [3.0.8] - React Stub Syntax Fix

### 🛠️ Major Template Fixes

- **Fixed Handlebars Syntax**: Replaced {{#each}} with proper PHP str_replace() syntax
- **Dynamic Field Generation**: All React stubs now use correct variable replacement
- **TypeScript Support**: Added proper interface field generation
- **Table Components**: Fixed table cell generation for Index component
- **Show Fields**: Simplified and fixed Show component field display

### 🔧 Enhanced Replacement Variables

- `{{fillableColumns}}` - JavaScript object fields for Create forms
- `{{editFillableColumns}}` - JavaScript object fields for Edit forms with model data
- `{{typeScriptColumns}}` - TypeScript interface field definitions
- `{{tableCells}}` - Table cell generation for Index component
- `{{showFieldsReact}}` - Display fields for Show component

### 📂 Updated React Stubs

- **Create.stub**: Fixed useForm field generation
- **Edit.stub**: Fixed useForm with model data population
- **Index.stub**: Fixed TypeScript interface and table cells
- **Show.stub**: Simplified field display, removed complex relations

---

## [3.0.7] - Critical Bug Fix

### 🐛 Bug Fixes

- **Fixed getFilteredColumns() Error**: Resolved TypeError when processing database columns
- **Database Column Processing**: Fixed explode() error on stdClass objects
- **Command Compatibility**: Now uses parent class getFilteredColumns() method correctly

---

## [3.0.6] - Laravel 12 Modernization Complete

### 🎉 Major Laravel 12 Compatibility Update

#### ✨ New Features

- **React Components**: Complete Laravel 12 compatibility
- **AppLayout Integration**: Updated from AuthenticatedLayout to AppLayout
- **Breadcrumbs System**: Added comprehensive breadcrumb navigation
- **Enhanced Form Handling**: Smart fillable fields detection for useForm
- **Route Organization**: Separate route files per model with proper middleware

#### 🔧 Technical Improvements

- **fillableColumns Support**: Dynamic field generation for React forms
- **Enhanced buildReplacements**: Added fillableColumns, modelRoutePlural, and other variables
- **Filtered Column Generation**: Excludes timestamps and system fields from forms
- **JavaScript Form Fields**: Proper useForm object generation
- **ModelRoutes.stub**: Template for separate route file organization

#### 📂 Updated Files

- **React Stubs**: All React components updated for Laravel 12
  - `Create.stub`: AppLayout + breadcrumbs + fillableColumns
  - `Edit.stub`: AppLayout + breadcrumbs + fillableColumns
  - `Index.stub`: AppLayout + breadcrumbs
  - `Show.stub`: AppLayout + breadcrumbs + router integration
- **ModelRoutes.stub**: New template for separate route files
- **InstallCommand.php**: Enhanced with fillableColumns support

#### 🛠️ Bug Fixes

- Fixed type compatibility issues in getFilteredColumns()
- Proper JavaScript object formatting for useForm
- Consistent method signatures and return types

---

## Previous Versions

### [3.0.3] - Command Namespace Fix

- Fixed command namespace from themes: to crud:
- Resolved "crud namespace not found" error

### [3.0.2] - Missing Stub Files

- Added missing ApiResourceCollection.stub
- Fixed Form.stub references

### [3.0.1] - Installation Fix

- Fixed abstract method implementations in InstallCommand
- Resolved installation blocking errors

### [3.0.0] - Initial Laravel 12 Release

- Base Laravel 12 compatibility
- React.js integration with Inertia.js
- Modern CRUD generator with themes
