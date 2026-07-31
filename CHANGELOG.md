# Changelog

## [5.0.0] - 2026-07-31

### ⚠️ Leia antes de atualizar

**O sistema de temas saiu do pacote — quem tem `crud:install-theme-system` instalado
precisa agir antes de atualizar.** No Laravel 13, ele quebrava o `tsc` do projeto com
`Property 'resolvedAppearance' does not exist`. A causa: o instalador antigo sobrescrevia
`resources/js/hooks/use-appearance.tsx` e `resources/js/components/appearance-tabs.tsx` —
os dois são do starter kit, não do pacote — e a versão publicada não expunha a API que o
kit atual usa. Não dava para corrigir sem reescrever o sistema inteiro, então ele foi
substituído por uma camada mais simples: a **paleta de cores**.

Rode `php artisan crud:install-palette`. Ele detecta o sistema antigo (`js/lib/themes.ts`),
oferece apagar o que **o pacote** instalou, e lista, para os arquivos que eram **do starter
kit** e foram sobrescritos, o comando para recuperá-los:

```bash
git checkout -- resources/js/hooks/use-appearance.tsx
git checkout -- resources/js/components/appearance-tabs.tsx
```

O pacote não apaga nem tenta restaurar esses dois — só o git do usuário sabe o que estava
neles antes.

**Se você gerou algum CRUD com a antiga flag `--theme`, regere-o.** As páginas
`Index`/`Create`/`Edit`/`Show` desse recurso ainda têm `import ThemeSelector from
'@/components/theme-selector'` e `import { useAppearance } from '@/hooks/use-appearance'`
materializados — a flag e os dois placeholders que ela preenchia saíram do gerador nesta
mesma release. `crud:install-palette` oferece apagar `theme-selector.tsx`; apagando sem
regerar o CRUD antes, a build quebra com import não resolvido. Regenerar é `php artisan
getic:install {tabela}` de novo, na mesma tabela.

### Removido

- Comandos `crud:install-theme-system` e `crud:create-theme`.
- Flag `--theme` do `getic:install`, o prompt correspondente no fluxo interativo, e os
  placeholders `{{themeImports}}` e `{{themeComponents}}`.
- `config/themes.php` e a chave global `config('themes')` que ele populava.
- Tag de publish `theme-system`.
- Tag de publish `crud-assets` — apontava para `src/stubs/js` e `src/stubs/css`, que nunca
  existiram.
- `CrudManager::getThemes()`.
- Os oito stubs de `src/stubs/react/` que o `crud:install-theme-system` instalava:
  `themes.ts`, `use-appearance.tsx`, `theme-selector.tsx`, `appearance-dropdown.tsx`,
  `appearance-theme-selector.tsx`, `appearance-tabs.tsx`, `theme-demo.tsx` e
  `ThemeExample.tsx`.

### Adicionado

- **`crud:install-palette`** — instala a camada de paleta nas stacks `react`, `vue` e
  `svelte` (detecta qual pelo projeto, ou aceita `--stack=`). Escreve dois arquivos
  compartilhados (`resources/css/crud-palettes.css`, `resources/js/lib/crud-palette.ts`) e
  um seletor específico da stack, e edita três arquivos do usuário de forma idempotente:
  `resources/css/app.css` (acrescenta o `@import` das paletas), o arquivo de entrada da
  stack (chama `initializeCrudPalette()` no mesmo ponto onde o starter kit já chama
  `initializeTheme()`) e a página de aparência (insere `<CrudPaletteSelector />` numa
  região marcada, ao lado do `<AppearanceTabs />` do próprio kit). Quando não reconhece o
  arquivo, **não escreve** — avisa e imprime o trecho para colar à mão.
- **`crud:create-palette {nome}`** — acrescenta uma paleta nova aos dois arquivos
  compartilhados.
- Tag de publish `crud-palette`.
- `config('crud.palette.settings_page')` — desliga a inserção automática do seletor na
  página de aparência, para quem prefere posicionar o componente à mão.
- Uma paleta define **nove variáveis de acento** (`--primary`, `--primary-foreground`,
  `--ring`, `--sidebar-primary`, `--sidebar-primary-foreground`, `--sidebar-ring`,
  `--chart-1`, `--chart-2`, `--chart-3`) e **nenhuma de superfície**. Superfície e texto são
  o que faz claro/escuro legível nos dois modos, e o starter kit já acertou esse contraste
  — a paleta não mexe nisso, só no acento.

### Migração

| Antes | Depois |
|---|---|
| `crud:install-theme-system` | `crud:install-palette` |
| `crud:create-theme {nome}` | `crud:create-palette {nome}` |
| Tag `theme-system` | Tag `crud-palette` |
| `config('themes.*')` | (removido — sem equivalente; a única chave nova é `config('crud.palette.settings_page')`) |

**CRUD gerado com `--theme`: regere.** A tabela acima é sobre os comandos de tema; a flag
`--theme` do `getic:install` é outra coisa — saiu junto, e as páginas que ela gerou
importam componentes que este release remove. Ver o aviso completo em "Leia antes de
atualizar", no topo deste changelog.

O seletor de paleta é independente do claro/escuro: a alternância `.dark` continua sendo
inteiramente do starter kit — nada do pacote observa, importa ou reaplica essa classe. O
pacote só escreve o atributo `data-crud-palette` no `<html>` e persiste a escolha em
`localStorage`.

### Fora de escopo

- Stack `livewire`: as variáveis de acento são do Flux (`--color-accent`), o seletor é
  Blade com Alpine, e a persistência é do store do Flux — desenho diferente o bastante para
  merecer spec própria. `crud:install-palette` continua sem suporte a ela.

### Por que major

Renomear dois comandos publicados (item 1 da API pública do `CLAUDE.md`) e remover uma tag
de publish (item 3) quebra quem automatiza a instalação com o nome antigo. Somado à
migração manual que quem tinha o sistema de temas instalado precisa fazer, não há leitura
possível como minor.

## [4.0.2] - 2026-07-30

### Corrigido

- **Os componentes react gerados quebravam o `npm run lint:check` do projeto.** Eram 23
  erros do eslint, todos em arquivo que o pacote escreve — e como as páginas `.tsx` são
  sobrescritas sem perguntar, o usuário via a build dele falhar sem ter editado nada. O
  `lint:check` é gate de CI nos starter kits.

  Os blocos de import dos seis stubs estavam fora da ordem que o `import/order` exige
  (`react`, `@inertiajs/react` e `lucide-react` depois dos `@/…`, e cada grupo fora da
  ordem alfabética), e a linha do wayfinder entrava colada no fim, depois de `@/types`.
  Agora ela entra na posição alfabética dela, entre `@/layouts` e `@/types`. Junto: os
  imports de `@/types` viraram `import type` (`consistent-type-imports`), o `if` de uma
  linha do `Index` ganhou chaves (`curly`), três instruções de controle ganharam a linha
  em branco que o `padding-line-between-statements` pede, e o `placeholder` que o
  `RichTextEditor` desestruturava sem usar saiu da desestruturação — a prop continua na
  interface, porque a área editável é um `contentEditable` e não tem onde pô-la, e tirá-la
  quebraria quem já passa.

  Verificado rodando o eslint de verdade contra a saída, no starter kit: 23 → 0, nos dois
  helpers de rota, com `tsc --noEmit` limpo.

### Interno

- `GeneratedLintContractTest` fixa as quatro regras que morderam — ordem dos grupos de
  import, ordem alfabética dentro de cada um, `import type` em `@/types`, chaves no `if` e
  linha em branco antes de instrução de controle — nos seis componentes e nos dois helpers.
  O eslint não roda aqui: config e plugins moram no projeto do usuário e o CI deste pacote
  não tem node.
- **PHPStan nível 5 virou gate de CI** (`phpstan.neon`, job `phpstan`). É a rede que faltava
  na 4.0.0: `return.missing` é reportado desde o nível 0 e não é suprimível, então aquele
  bug não passaria do commit. Deixar verde custou quatro correções internas — chave
  `{{relations}}` duplicada no mapa de replacements, `match` do pré-voo sem `default` (um
  código de achado novo era `UnhandledMatchError` no terminal), `@return $this` que o
  `buildViews()` não cumpre, e um `??` morto no `CrudManager`. Nada disso muda arquivo
  gerado.

### Conhecido, não corrigido

- `npx prettier --check` ainda reprova os seis arquivos gerados: os stubs têm linhas acima
  das 80 colunas do `printWidth` dos starter kits. É anterior a esta release — o backup dos
  arquivos de antes reprova igual — e alinhar exigiria requebrar os stubs inteiros.
- `--routes=ziggy` num projeto sem ziggy gera código que não compila (`Cannot find name
  'route'`). O `auto` não cai nisso, porque escolhe wayfinder quando ele está instalado.

## [4.0.1] - 2026-07-30

### Corrigido

- **A 4.0.0 não gerava nada em react com wayfinder.** O comando morria no primeiro
  componente com `TypeError: getRouteImports(): Return value must be of type string, none
  returned`. A remoção da API na 4.0.0 apagou o `_getFormRequestPath()` uma linha antes do
  início dele: o hunk começou no `return` do `getRouteImports()` e terminou antes da chave
  de fechamento do `_getFormRequestPath`, então a chave que sobrou passou a fechar o
  `getRouteImports()` — sem `return` nenhum no caminho do wayfinder. O modo ziggy não era
  afetado, porque retorna `''` antes de chegar ali; o wayfinder é o default em projeto com
  `laravel/wayfinder` instalado, que é o caso dos starter kits do Laravel 13.

  **Atualize direto para a 4.0.1.** A 4.0.0 não serve para nada em react.

### Interno

- `InstallCommandRouteImportsTest`: a linha de import exata por componente, ziggy e
  componente sem rota devolvendo string vazia, `--route` movendo o módulo importado, e o
  contrato que faltava — toda chamada `xRoute()` de um stub renderizado tem que estar na
  linha de import daquele componente. É o teste que a 4.0.0 não tinha: `php -l` vê arquivo
  válido, então só execução (ou análise estática) pega retorno faltando.

## [4.0.0] - 2026-07-30

### ⚠️ Leia antes de atualizar

**A geração de API saiu do pacote.** A flag `--api`, o prompt "Deseja gerar endpoints de
API RESTful?" e os cinco stubs correspondentes foram removidos. Passar `--api` agora é
erro do Symfony ("The `--api` option does not exist"), em vez de gerar arquivos quebrados
em silêncio.

Isto **não deve quebrar nenhum projeto em produção**, porque a feature nunca funcionou em
release nenhuma:

- Os stubs pressupunham um motor de templating handlebars (`{{#each}}`, `{{#if (eq type
  'string')}}`) que jamais foi escrito — a substituição do pacote sempre foi `str_replace`
  sobre um mapa plano, então esses blocos iam literais para o arquivo do usuário.
- Dos 29 placeholders usados pelos stubs de API, **21 nunca estiveram no mapa de
  replacements** — inclusive `{{namespace}}`, que é a linha 3 do `ApiController` gerado.
  O arquivo não passava do parse.
- Mesmo com PHP válido, as rotas não carregariam: desde o Laravel 11 o `routes/api.php` só
  é registrado depois de `php artisan install:api`, e o pacote nunca fez essa parte. O stub
  ainda pedia `auth:sanctum` num starter kit que não traz o Sanctum, e um limiter
  `throttle:public-api` que não existe.

Se você tem arquivos gerados por `--api` no projeto, eles continuam onde estão — o pacote
não apaga nada. Eles é que nunca funcionaram.

**Chaves de config removidas:** `api.*` e `validation.*`. Nenhuma delas era lida por
código nenhum. Se você publicou o `config/crud.php`, pode apagar as duas seções.

### Removido

- Flag `--api` do `getic:install` e o prompt de API do fluxo interativo.
- Os builders `buildApiController()`, `buildApiRoutes()`, `buildApiResources()`,
  `buildApiResource()` e `buildFormRequest()`, mais os quatro helpers de caminho deles, e
  os quatro métodos abstratos correspondentes em `GeneratorCommand`.
- Os stubs `ApiController`, `ApiRoutes`, `ApiResource`, `ApiResourceCollection` e
  `FormRequest` — 660 linhas.
- As chaves `api` e `validation` de `src/config/crud.php`.

### Por que major

Remover flag documentada de comando Artisan é o item 1 da API pública listada no
`CLAUDE.md`. A régua escrita na 3.3.1 diz que isso é breaking independentemente de a
feature funcionar, e é ela que decide aqui — não o julgamento caso a caso.

## [3.3.1] - 2026-07-30

### Corrigido

- **Regerar uma tabela depois de `npm run format` corrompia o `app-sidebar.tsx`.** O
  prettier do starter kit quebra o item da navegação em várias linhas assim que ele passa
  das 80 colunas, o que um nome de tabela médio provoca — o item de
  `configuracoes_sistema` tem 88. A idempotência do pacote é linha a linha, então ela
  casava só a linha do `href:` e a substituía no lugar, deixando as irmãs órfãs e o TSX
  inválido, gravado sem pergunta e sem backup. Agora o pacote confere que cada item cabe
  numa linha antes de tocar no arquivo; quando não cabe, ele não altera nada, diz que o
  item foi reformatado e imprime a linha para você ajustar à mão.
- **`eslint .` passava a falhar depois do install.** O import do ícone entrava depois do
  último import de uma linha qualquer, o que no `app-sidebar.tsx` dos starter kits o punha
  depois dos imports `@/` e violava a regra `import/order`. Como `npm run lint:check` é
  gate de CI deles, a build quebrava por causa da nossa edição. O import agora entra no
  grupo dos módulos externos.
- **Breadcrumbs de `Show` e `Edit` mostravam o href literal.** O template literal saía sem
  `${}`, então a trilha exibia o texto cru em vez do id do registro.
- **O import do ícone não voltava quando sumia.** Ele só era garantido na criação da
  região, e trocar o ícone à mão deixa o `CrudNavIcon` órfão — que qualquer organizador de
  imports remove. A geração seguinte reescrevia `icon: CrudNavIcon` num arquivo que não
  importava mais o símbolo, e a build do usuário parava. Agora a linha é reafirmada a cada
  geração (sem duplicar, se já estiver lá).

### Documentação

- A tabela de arquivos escritos dizia "criado" para `routes/{model}.php` e para as páginas
  `.tsx`, mas os dois são **sobrescritos sem perguntar**. Só o Controller e o Model
  perguntam.
- O exemplo de rotas do README mostrava `/products/bulk` e punha a rota curinga antes da
  de bulk — a ordem que o comentário do próprio stub diz não usar.
- O exemplo de `config/crud.php` do README estava várias releases atrasado: layout errado,
  uma chave `theme_integration` que não existe, e sem `route_helper`, `navigation`,
  `api.prefix` nem `api.middleware`.
- `--route` também move o segmento das rotas de `--api`, não só o das rotas web.

### Interno

- `NavigationRegion::upsert()` passou a receber a linha de import como quarto argumento.
  A classe é colaboradora interna do `getic:install` e não faz parte da API pública
  listada no `CLAUDE.md`.
- A matrix do CI ganhou um job `--prefer-lowest` em PHP 8.2 — o piso do `composer.json`,
  que só existe com Laravel 12 e não era exercitado por nenhum job.

## [3.3.0] - 2026-07-30

### Adicionado

- **Pré-voo da tabela.** Antes de escrever qualquer arquivo do CRUD, o `getic:install` inspeciona
  a tabela e avisa o que o código gerado não suporta: falta de `created_at` e/ou `updated_at`
  (a listagem gerada ordena por `created_at` e falha no banco), chave primária ausente ou
  com nome diferente de `id` (o `Index` usa `id` para a key da linha e para os links), e
  coluna cujo nome não é um identificador válido (o Controller e o tipo TypeScript não
  compilam). **Avisa, não bloqueia:** você confirma e a geração segue. Em modo não
  interativo ele avisa, segue, e repete o resumo no fim da execução. Numa tabela
  convencional é silencioso — não pergunta nada. O padrão da pergunta é gerar (apertar Enter
  continua). O comando passa a parar e perguntar em tabela fora da convenção, onde antes
  seguia direto; quem automatiza usa `--no-interaction`, caso em que ele avisa e segue sem
  bloquear.
  
  Uma exceção: se você aceitar instalar o sistema de temas no prompt inicial, ele é instalado
  antes, porque roda na fase de perguntas do próprio Artisan — cancelar no pré-voo não desfaz
  isso.

### Removido

- `debugColumns()`, que despejava o JSON de todas as colunas no terminal em toda geração,
  e o fazia depois de os arquivos já estarem escritos. O pré-voo entrega, antes da
  escrita, o que ele fingia entregar.

## [3.2.0] - 2026-07-29

### ⚠️ Leia antes de atualizar

**`getic:install {tabela}` sem `--stack` mudou de saída.** Até a 3.1.4 esse comando gerava
um Controller Blade clássico e **nenhuma view**. Agora gera a stack `react` inteira:
`InertiaController`, os componentes `Index`/`Create`/`Edit`/`Show`, o tipo TypeScript,
`ui/table.tsx` e `ui/pagination.tsx` se faltarem, e um item no `app-sidebar.tsx`. O
Controller é o arquivo que mais se edita à mão, então **não regere sobre um Controller
que você já customizou sem antes conferir o diff** — o pacote pergunta antes de
sobrescrever, e não existe flag para pular essa pergunta. Em modo não interativo a
resposta padrão é **não** sobrescrever: o Controller existente fica como está e o resto
da geração segue.

Quem quer a saída antiga passa `--stack=blade`, mas note que `buildBladeViews()` ainda é
vazio: só o Controller sai, sem views.

**A URL da listagem mudou: `GET /{recurso}/index` deixou de existir, e agora é
`GET /{recurso}`.** O nome da rota continua `{recurso}.index`, então `route()` e os
imports do wayfinder não mudam — mas qualquer link, favorito, teste ou integração que
aponte para a URL antiga passa a dar 404.

**Regerar sobrescreve `routes/{model}.php` e as páginas `.tsx` sem perguntar.** As duas
coisas acontecem na mesma release em que o conteúdo desses arquivos mudou por inteiro, o
que torna "regerar" destrutivo para quem os editou à mão. Confira o diff antes, ou guarde
uma cópia.

**Arquivos que o pacote escreve ou edita no seu projeto** (stack `react`). O Controller e o
Model são a exceção, não a regra: só eles perguntam antes de sobrescrever. Regerar uma
tabela substitui o arquivo de rotas e as páginas `.tsx` sem aviso, então guarde à parte o
que editou neles.

| Caminho | O que acontece |
|---|---|
| `app/Http/Controllers/{Model}Controller.php` | criado (pergunta antes de sobrescrever) |
| `app/Models/{Model}.php` | criado (pergunta antes de sobrescrever) |
| `routes/{model}.php` | criado — e **sobrescrito sem perguntar** se já existir |
| `routes/web.php` | ganha um `require` idempotente |
| `resources/js/pages/{Model}/*.tsx` | criados — e **sobrescritos sem perguntar** se já existirem |
| `resources/js/types/{model}.ts` ou `types/index.d.ts` | criado, conforme o layout do app |
| `resources/js/types/paginated.ts` | criado só se faltar, nunca sobrescrito |
| `resources/js/types/index.ts` | ganha `export type * from './{model}';` — **editado sem perguntar** |
| `resources/js/components/ui/table.tsx`, `ui/pagination.tsx` | criados só se faltarem, nunca sobrescritos |
| `resources/js/components/app-sidebar.tsx` | ganha a região `crud:nav:*` (pergunta na primeira vez) |
| `resources/js/routes/**` | regerado pelo `wayfinder:generate`, quando há wayfinder |

### Adicionado

- Suporte a **Laravel 13**, mantendo o Laravel 12. `illuminate/*` passa a aceitar
  `^12.0|^13.0`. Suíte executada contra o 13 (Testbench 11, PHPUnit 12).
- Suporte ao **`laravel/wayfinder`** nos componentes React gerados: as rotas viram funções
  TypeScript importadas de `@/routes/{recurso}`, em vez de chamadas ao helper global
  `route()`. Nova config `crud.inertia.route_helper` (`auto`, `ziggy`, `wayfinder`) e flag
  `--routes=`. Em `auto`, o pacote detecta se o `laravel/wayfinder` está instalado no
  projeto. **No eixo wayfinder-vs-ziggy, quem não tem wayfinder recebe a mesma saída do
  modo ziggy** — verificado por comparação byte a byte dos componentes gerados. Isso não
  quer dizer "igual à 3.1.4": `Index`, `Create`, `Edit`, `Show`, `FormField`, os tipos e
  as rotas mudaram nesta release para todo mundo (ver a seção acima e as correções abaixo).
  O modo wayfinder emite `submit(storeRoute())`, que depende de `useForm().submit()`
  aceitar um par `{ url, method }`. Verificado com `@inertiajs/react` **3.6.1**, a versão
  que os starter kits do Laravel 13 instalam sob `^3.0.0`
  (`UseFormSubmitArguments` aceita `[UrlMethodPair, options?]`). O piso exato dentro da
  linha 3.x não foi determinado — se você fixou uma 3.x anterior, rode `tsc` antes de
  subir para produção.
- A stack `react` passa a instalar os componentes `table` e `pagination` do shadcn/ui
  em `resources/js/components/ui/`, que os stubs importam mas os starter kits do Laravel
  não trazem. Nenhuma dependência npm nova: ambos usam apenas o que o starter kit já tem.
  Componente já existente **nunca** é sobrescrito.
- A stack `react` passa a inserir um link para o CRUD gerado na sidebar do projeto,
  numa região delimitada pelos comentários `crud:nav:start` / `crud:nav:end` que o
  pacote gerencia sozinho — o resto do seu menu nunca é tocado. Na primeira geração o
  pacote pergunta antes de criar a região. Desligue com `crud.navigation.sidebar => false`.

### Alterado

- **Mudança de comportamento:** `--stack` passa a ser honrado fora do prompt interativo.
  Até a 3.1.4 a flag era ignorada quando o nome da tabela vinha na linha de comando, e
  **nenhuma view era gerada — inclusive para `--stack=react`**, apesar de o README
  documentar esse uso. Uma stack desconhecida agora falha com mensagem clara, em vez de
  gerar nada em silêncio.
- Os componentes `Edit` e `Show` gerados não dependem mais de `PageProps` de `@/types`,
  que o starter kit do Laravel 13 não exporta. Nenhum dos dois usava o prop `auth` que
  ele fornecia, então a mudança também vale para Laravel 12 — só remove uma dependência
  desnecessária.
- **A rota de listagem passou de `GET /{recurso}/index` para `GET /{recurso}`**, que é o
  layout que o próprio Laravel usa em `Route::resource`. O nome continua
  `{recurso}.index`, então nenhum `route('clientes.index')` nem import do wayfinder muda;
  o que muda é a URL, e `/clientes/index` deixa de existir. Sem essa rota, o link novo da
  sidebar dava 404.
- `symfony/console` passa a aceitar `^8.0` (o Laravel 13 aceita `^7.4|^8.0`, e o pacote
  preso em `^7.0` bloqueava quem migrasse para o Symfony 8), e `symfony/process` — usado
  desde esta release para rodar o `wayfinder:generate` — passa a ser declarado em vez de
  depender de o framework trazê-lo.
- O campo `version` saiu do `composer.json`: o Packagist deriva a versão da tag do git.

### Corrigido

- JSX inválido nos componentes React gerados. `getTableCellsForIndex()` produzia
  `{{model}.campo}` em vez de `{model.campo}`, uma célula quebrada por coluna; e
  `Edit.stub`/`Show.stub` vazavam o idioma de escape do Blade (`{{'{'}}`) para dentro de
  arquivos `.tsx`, que nunca passam pelo compilador Blade.
- `Edit.stub` injetava campos de formulário com `<Label>` e `<Input>` sem importar
  nenhum dos dois.
- `Show.stub` importava `DangerButton`, `SecondaryButton` e `PrimaryButton` de
  `@/Components` — caminho do Breeze que não existe mais — e não usava nenhum dos três.
- O tipo TypeScript do model era escrito em `resources/js/types/index.d.ts`, mas o
  starter kit do Laravel 13 usa `index.ts` como barrel, e o TypeScript resolve `index.ts`
  primeiro — o tipo gerado nunca era lido. Agora o layout é detectado: com barrel, gera
  um arquivo por model e registra no `index.ts`; sem barrel, mantém o `index.d.ts` de antes.
- Regerar a mesma tabela substitui o bloco de tipos em vez de anexar `Cliente2`,
  `Cliente3` e assim por diante — versões que nenhum componente gerado importava.
- A rota `bulk-destroy` nunca era declarada, embora o `Index` gerado a chamasse e o
  Controller definisse `bulkDestroy()`. A exclusão em massa quebrava em runtime. A rota
  é declarada **antes** da curinga `{model}`, senão `DELETE /clientes/bulk-destroy` seria
  lido como `DELETE /clientes/{cliente}`.
- `FormField` importava `useTheme` de `@/hooks/use-appearance`, que não exporta esse nome.
  O valor era desestruturado e nunca usado, então o import saiu junto.
- O `onChange` do upload de arquivo era tipado como arquivo único, mas o componente já
  aceitava `multiple` e devolvia uma lista nesse caso.
- O item que o pacote escreve na sidebar apontava para uma URL que o arquivo de rotas
  gerado nunca declarava: clicar no link dava 404.
- `--route` movia as rotas mas não o link da sidebar nem os breadcrumbs dos componentes
  `Index`, `Create`, `Edit` e `Show` — sete lugares recalculavam o segmento da URL por
  conta própria. Agora todos leem a mesma fonte, o que inclui as rotas de `--api`: o
  segmento de `Route::apiResource` passa a acompanhar a flag, e não só as rotas web.
- O import do ícone na sidebar vinha sem apelido. Num projeto que já importava `List` do
  `lucide-react`, o identificador ficava ligado duas vezes: erro de compilação em
  TypeScript (`TS2300: Duplicate identifier`) e `SyntaxError` em ES modules. O import
  agora é `List as CrudNavIcon`.
- A inserção do import caía dentro das chaves de um import multilinha, deixando o
  `app-sidebar.tsx` sem compilar. Os starter kits importam `ui/sidebar` exatamente assim.
- O `wayfinder:generate` disparado pelo pacote rodava sem `--with-form` e reescrevia
  `resources/js/routes` inteiro, apagando as variantes de form das rotas do próprio
  projeto — as páginas de auth e settings do starter kit paravam de compilar com
  `Property 'form' does not exist`. O formato agora é lido da saída existente e
  preservado.

Com isso, o CRUD gerado passa no `tsc` **sem nenhum erro** — e o resto do app também.

## [3.0.19] a [3.1.4] - não documentadas

Estas releases saíram sem entrada no changelog. Ver `git log v3.0.18..v3.1.4`.

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
