# Changelog

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
