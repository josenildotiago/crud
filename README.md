# Laravel CRUD Generator v5.0.0

[![tests](https://github.com/josenildotiago/crud/actions/workflows/tests.yml/badge.svg)](https://github.com/josenildotiago/crud/actions/workflows/tests.yml)

Um pacote moderno para Laravel que gera operações CRUD completas com integração React.js e uma camada de paleta de cores.

## 🚀 Características Principais

### ✨ Compatibilidade Laravel 12 e 13

- **Arquitetura Moderna**: Laravel 12 e 13, com suporte PHP 8.2+
- **Wayfinder**: em projetos com `laravel/wayfinder`, as rotas viram funções TypeScript
  importadas de `@/routes/{recurso}` no lugar do helper global `route()`
- **Link na Sidebar**: o CRUD gerado entra no menu do projeto numa região que o pacote
  gerencia sozinho
- **Integração AppLayout**: Usa AppLayout (ao invés do AuthenticatedLayout descontinuado)
- **Sistema de Breadcrumbs**: Navegação hierárquica abrangente
- **Campos Inteligentes**: Detecção automática de campos fillable para React useForm
- **Organização de Rotas**: Arquivos de rota separados por modelo com middleware adequado
- **Suporte Multi-Database**: Compatível com MySQL, PostgreSQL, SQLite e SQL Server
- **Design RESTful**: Gera controllers e rotas RESTful limpos

### 🎨 Camada de Paleta de Cores

- **Sistema de Cores OKLCH**: Suporte ao espaço de cor moderno para paletas vibrantes e consistentes
- **CSS Custom Properties**: Troca de paleta em tempo real, sem reload da página
- **React, Vue e Svelte**: Um seletor por stack, instalado dentro da página de aparência do starter kit
- **Persistente**: Preferência salva em `localStorage`
- **Criação Automática**: Comando para acrescentar novas paletas

### ⚛️ Integração React.js + shadcn/ui

- **Suporte Inertia.js**: SSR sem complicações com backend Laravel
- **TypeScript Pronto**: Suporte completo ao TypeScript para type safety
- **Componentes Modernos**: AppLayout com navegação breadcrumb
- **shadcn/ui Integration**: Uso completo de Button, Card, Input, Label
- **Formulários Inteligentes**: Integração fillableColumns com useForm
- **Design Responsivo**: Padrões mobile-first

### 🛠️ Funcionalidades CRUD Avançadas

- **Operações em Lote**: Ações multi-select para gerenciamento eficiente de dados
- **Busca Avançada**: Busca em tempo real com debouncing
- **Paginação Inteligente**: Paginação otimizada com preservação de estado
- **Validação de Formulários**: Validação client e server-side
- **Upload de Arquivos**: Manipulação integrada de arquivos com preview
- **Export/Import**: Exportação CSV com colunas customizáveis

## 📦 Instalação

```bash
composer require josenildotiago/crud
```

## 🎯 Início Rápido

### 1. Instalar a Camada de Paleta (Opcional)

```bash
php artisan crud:install-palette
```

Este comando irá:

- Detectar seu stack frontend (`react`, `vue` ou `svelte` — pela página de aparência do
  projeto; ou aceita `--stack=` explícito)
- Escrever `resources/css/crud-palettes.css` e `resources/js/lib/crud-palette.ts`
- Gerar o seletor de paleta da stack detectada
- Editar `resources/css/app.css`, o arquivo de entrada da stack e a página de aparência,
  sempre de forma idempotente — rodar de novo não duplica nada

### 2. Criar Sua Primeira Paleta (Opcional)

```bash
php artisan crud:create-palette minha-paleta
```

Prompts interativos irão guiá-lo através de:

- Nome e identificador da paleta
- Matiz OKLCH (0 a 360)
- Geração automática dos dois modos, claro e escuro

### 3. Gerar Recursos CRUD

```bash
php artisan getic:install users
```

Este comando gera:

- **Model**: `app/Models/User.php` com relacionamentos
- **Controller**: `app/Http/Controllers/UserController.php` otimizado para Inertia.js
- **Componentes React**: Interface CRUD completa em TypeScript
  - `Create.tsx` - Formulário de criação com shadcn/ui
  - `Edit.tsx` - Formulário de edição
  - `Index.tsx` - Listagem com paginação e busca
  - `Show.tsx` - Visualização de registro
- **Routes**: `routes/user.php` com middleware auth e verified

#### Opções Avançadas

```bash
# Com relacionamentos automáticos
php artisan getic:install orders --relationship

# Stack específico
php artisan getic:install categories --stack=react

# Escolhendo o helper de rotas dos componentes React
php artisan getic:install clientes --routes=wayfinder
php artisan getic:install clientes --routes=ziggy
```

#### Helper de rotas (`--routes=`)

Sem a flag, vale `crud.inertia.route_helper` — que por padrão é `auto` e detecta se o
`laravel/wayfinder` está instalado no projeto. Em `wayfinder`, os componentes importam
funções de `@/routes/{recurso}`; em `ziggy`, usam o helper global `route()`.

#### Link na sidebar

Na stack `react`, o pacote acrescenta o CRUD gerado ao menu do projeto
(`resources/js/components/app-sidebar.tsx`), dentro de uma região delimitada pelos
comentários `crud:nav:start` / `crud:nav:end` — o resto do seu menu nunca é tocado, e
regerar a mesma tabela substitui o item em vez de duplicar. Na primeira geração o pacote
pergunta antes de criar a região. Para desligar:

```php
// config/crud.php
'navigation' => [
    'sidebar' => false,
],
```

#### Pré-voo da tabela

Antes de escrever qualquer arquivo do CRUD, o pacote confere se a tabela tem o que o código
gerado assume: `created_at` e/ou `updated_at`, chave primária chamada `id`, e nomes de coluna
que sejam identificadores válidos. Se algo falta, ele lista os avisos e pergunta se você
quer gerar mesmo assim — **avisa, não bloqueia**, porque gerar em cima de uma tabela
legada e ajustar o Model à mão depois é caso legítimo. Numa tabela criada pelas migrations
do Laravel ele não diz nada.

Em modo não interativo (`--no-interaction`, script, CI) ele imprime os avisos, segue, e
repete o resumo no fim — por sua conta e risco. O padrão da pergunta é gerar (apertar Enter
continua).

`getic:install` não instala a paleta — os dois comandos são independentes; rode
`crud:install-palette` quando quiser.

## 🎨 Paleta de Cores

As paletas mudam só o **acento** da interface: `--primary`, `--primary-foreground`,
`--ring`, `--sidebar-primary`, `--sidebar-primary-foreground`, `--sidebar-ring`,
`--chart-1`, `--chart-2` e `--chart-3`. Nenhuma variável de superfície entra — cor de fundo
e de texto continuam do starter kit, que já acerta o contraste nos dois modos.

O claro/escuro **não é do pacote**: continua sendo a classe `.dark` que o starter kit já
gerencia. A paleta escreve só o atributo `data-crud-palette` no `<html>` e guarda a escolha
em `localStorage` — as duas dimensões, cor e modo, são independentes uma da outra.

### Instalação

```bash
php artisan crud:install-palette
```

O comando escreve três arquivos e edita três:

| Arquivo | O que acontece |
|---|---|
| `resources/css/crud-palettes.css` | Criado — as paletas, como seletores `:root[data-crud-palette='x']` |
| `resources/js/lib/crud-palette.ts` | Criado — a lista de paletas e a função que aplica o atributo |
| Seletor da stack — `crud-palette-selector.tsx` (react), `CrudPaletteSelector.vue` (vue) ou `CrudPaletteSelector.svelte` (svelte) | Criado |
| `resources/css/app.css` | Editado — acrescenta o `@import` das paletas |
| Arquivo de entrada da stack (`app.tsx` ou `app.ts`) | Editado — chama `initializeCrudPalette()` |
| Página de aparência (`settings/appearance`) | Editado — insere `<CrudPaletteSelector />` numa região marcada |

Rodar de novo é seguro: cada edição é idempotente, e nada é duplicado. Quando o comando não
reconhece o arquivo (versão do starter kit fora do padrão, edição manual que mudou a âncora
esperada), ele **não escreve** — avisa e imprime o trecho para você colar à mão.

Desligue a inserção automática do seletor na página de aparência com:

```php
// config/crud.php
'palette' => [
    'settings_page' => false,
],
```

O pacote guarda a escolha só em `localStorage`. A paleta é aplicada no arquivo de entrada
(`initializeCrudPalette()`, chamado no mesmo ponto onde o starter kit já chama
`initializeTheme()`), então pode haver um piscar breve da paleta padrão no primeiro
carregamento — só o acento muda de cor por um instante, o fundo e o texto não invertem.
Se isso incomodar, dá para eliminar gravando a preferência também num cookie e renderizando
o atributo `data-crud-palette` direto no HTML inicial (`app.blade.php`), mas isso é por sua
conta: o pacote não grava cookie nenhum hoje.

### Criar uma paleta nova

```bash
php artisan crud:create-palette minha-paleta
```

## 📋 Comandos Disponíveis

```bash
# Instalar a camada de paleta
php artisan crud:install-palette

# Criar uma paleta nova
php artisan crud:create-palette {nome}

# Gerar CRUD completo
php artisan getic:install {tabela}

# Com relacionamentos
php artisan getic:install {tabela} --relationship

# Escolhendo o helper de rotas dos componentes React (default: auto)
php artisan getic:install {tabela} --routes=wayfinder
```

## 🎯 Exemplo de Uso Completo

### 1. Instalação e Configuração

```bash
# Instalar pacote
composer require josenildotiago/crud

# Instalar a camada de paleta
php artisan crud:install-palette

# Criar uma paleta personalizada
php artisan crud:create-palette corporativo
```

### 2. Gerar CRUD para Produtos

```bash
php artisan getic:install products
```

### 3. Resultado Gerado

#### Controller (`app/Http/Controllers/ProductController.php`)

```php
class ProductController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $products = Product::query()
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->withQueryString()
            ->through(fn ($product) => [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'price' => $product->price,
                'created_at' => $product->created_at->format('d/m/Y H:i'),
                'updated_at' => $product->updated_at->format('d/m/Y H:i'),
            ]);

        return Inertia::render('Product/Index', [
            'products' => $products,
            'filters' => ['search' => $request->search],
        ]);
    }
}
```

#### Componente React (`resources/js/pages/Product/Create.tsx`)

```tsx
export default function Create() {
  const { data, setData, post, processing, errors } = useForm({
    name: "",
    description: "",
    price: "",
  });

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Criar Produto" />
      <form onSubmit={handleSubmit}>
        <Card className="container mx-auto py-8">
          <CardHeader>
            <CardTitle className="uppercase">Cadastrar novo produto</CardTitle>
            <CardDescription>Cadastre um novo produto</CardDescription>
          </CardHeader>
          <CardContent className="container">
            <div className="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-12">
              <div className="sm:col-span-12">
                <Label htmlFor="name">Nome:</Label>
                <Input
                  placeholder="Digite o nome"
                  value={data.name}
                  onChange={(e) => setData("name", e.target.value)}
                  required
                />
                {errors.name && (
                  <p className="text-sm text-red-500 mt-1">{errors.name}</p>
                )}
              </div>
              {/* Outros campos gerados automaticamente */}
            </div>
          </CardContent>
          <CardFooter className="flex-col gap-2">
            <Button disabled={processing} className="w-full">
              {processing && <LoaderCircle className="h-4 w-4 animate-spin" />} Cadastrar
            </Button>
          </CardFooter>
        </Card>
      </form>
    </AppLayout>
  );
}
```

#### Rotas (`routes/product.php`)

```php
<?php

use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
    Route::post('/products', [ProductController::class, 'store'])->name('products.store');
    // Precisa vir antes da rota curinga abaixo, senão `bulk-destroy` é lido como {product}.
    Route::delete('/products/bulk-destroy', [ProductController::class, 'bulkDestroy'])->name('products.bulk-destroy');
    Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');
    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
    Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
});
```

## 🔧 Configuração Avançada

### Arquivo de Configuração

Publique e customize as configurações:

```bash
php artisan vendor:publish --provider="Crud\CrudServiceProvider" --tag="crud-config"
```

Arquivo `config/crud.php`:

Um recorte das chaves que mais se mexe — o arquivo publicado traz todas, comentadas:

```php
return [
    'frontend' => 'react', // blade, react, vue

    'inertia' => [
        'enabled' => true,
        'components_path' => 'js/pages',
        'layout_component' => 'Layouts/AuthenticatedLayout',

        // 'auto' usa wayfinder se estiver instalado, senão o route() do Ziggy.
        // Também aceita 'wayfinder' e 'ziggy'. A flag --routes= sobrepõe.
        'route_helper' => 'auto',
    ],

    // Em false, o pacote nunca toca no arquivo de navegação do projeto.
    'navigation' => [
        'sidebar' => true,
    ],

    // Em false, `crud:install-palette` não insere o seletor na página de aparência.
    'palette' => [
        'settings_page' => true,
    ],
];
```

## 📱 Características da Interface

### Componentes shadcn/ui

- **Cards**: Layout moderno com header, content e footer
- **Buttons**: Com estados de loading e ícones
- **Inputs**: Com labels e validação integrada
- **Tables**: Responsivas com paginação
- **Forms**: Grid responsivo e validação em tempo real

### Design Responsivo

- **Mobile-first**: Otimizado para dispositivos móveis
- **Grid System**: sm:grid-cols-12 para layout flexível
- **Breakpoints**: Tailwind CSS responsivo
- **Touch-friendly**: Interface amigável ao toque

## 🚀 Performance

### Otimizações

- **Lazy Loading**: Componentes carregados sob demanda
- **Code Splitting**: Divisão automática de código
- **CSS Optimization**: Custom properties para a paleta de cores
- **Database Queries**: Queries otimizadas com Eloquent

### Caching

- **Template Caching**: Stubs em cache durante desenvolvimento
- **Query Caching**: Colunas de banco em cache
- **Asset Optimization**: CSS e JS otimizados para produção

## 🧪 Testes

Execute os testes do pacote:

```bash
vendor/bin/phpunit
# ou
vendor/bin/pest
```

### Cobertura

- **Unit Tests**: Commands, Manager, Generator
- **Integration Tests**: Geração completa de CRUD
- **Component Tests**: Paleta e componentes React

## 📚 Documentação Adicional

- **[Documentação Técnica](DOC.md)**: Arquitetura detalhada do sistema
- **[Changelog](CHANGELOG.md)**: Histórico de versões e mudanças
- **[Contribuição](CONTRIBUTING.md)**: Como contribuir para o projeto

## 🤝 Contribuição

Contribuições são bem-vindas! Por favor, leia o guia de contribuição para detalhes sobre nosso código de conduta e o processo para enviar pull requests.

## 📄 Licença

Este projeto está licenciado sob a Licença MIT - veja o arquivo [LICENSE](LICENSE) para detalhes.

## 🆘 Suporte

Se você encontrar algum problema ou tiver dúvidas:

1. **Issues**: Abra uma issue no GitHub
2. **Discussões**: Use as discussões do GitHub para perguntas
3. **Email**: josenildo.tiago.designer@gmail.com

## 🎉 Créditos

Desenvolvido com ❤️ por [Josenildo Tiago](https://github.com/josenildotiago)

### Tecnologias Utilizadas

- **Laravel 12**: Framework PHP moderno
- **React.js**: Biblioteca JavaScript para UI
- **Inertia.js**: Stack moderno sem API
- **TypeScript**: JavaScript tipado
- **shadcn/ui**: Componentes React modernos
- **Tailwind CSS**: Framework CSS utility-first
- **OKLCH**: Espaço de cor moderno
