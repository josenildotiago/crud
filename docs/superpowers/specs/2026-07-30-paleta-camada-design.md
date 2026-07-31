# Paleta de cores como camada sobre o starter kit

Data: 30/07/2026
Status: aprovado, aguardando plano de implementação
Versão alvo: 5.0.0 (major)

## Problema

O sistema de temas do pacote foi escrito contra o starter kit do Laravel 12 e **quebra o
starter kit do 13**. Medido em 30/07/2026 no `projeto-exemplo-react`, rodando
`crud:install-theme-system --force` num projeto com `tsc --noEmit` e `eslint .` limpos:

```
resources/js/components/two-factor-setup-modal.tsx(65,13): error TS2339:
Property 'resolvedAppearance' does not exist on type '{ appearance; themeId;
updateAppearance; updateTheme; }'
```

A causa é a fronteira: o instalador **sobrescreve dois arquivos que são do starter kit**.

| Arquivo | Do kit 13 | Do pacote |
|---|---|---|
| `hooks/use-appearance.tsx` | `useSyncExternalStore`, expõe `resolvedAppearance`, `ResolvedAppearance`, `UseAppearanceReturn` | `useState`/`useEffect`, expõe `appearance`, `themeId`, `updateAppearance`, `updateTheme` |
| `components/appearance-tabs.tsx` | versão do kit | versão do pacote, escrita para o 12 |

Além disso instala `appearance-dropdown.tsx`, que no kit 13 não existe mais, acrescenta
`@radix-ui/react-tabs` ao `package.json`, que o kit 13 não usa, e os arquivos instalados
somam 13 erros de `eslint`.

Três defeitos menores no mesmo território:

1. **A resposta interativa de `--theme` é descartada.** `afterPromptingForMissingArguments()`
   grava em `$this->options['theme']`, mas `buildReactComponents()` lê `$this->option('theme')`.
   Responder "sim" instala o sistema de temas e gera os componentes **sem** tema.
2. Aceitar o tema no prompt instala durante a fase de perguntas do Artisan, **antes** do
   pré-voo da tabela: cancelar no pré-voo não desfaz a instalação.
3. `mergeConfigFrom(..., 'themes')` ocupa a chave global `config('themes')` e a tag de
   publish `theme-system` não é prefixada. Ambas colidem com outros pacotes.

## O que muda de concepção

O starter kit do 13 **já resolve claro/escuro/sistema**, com hook próprio e classe `.dark`
no `<html>`. O pacote não tem por que ter opinião sobre isso. O produto passa a ser só a
paleta de cores, e o pacote nunca toca no que é do kit.

O que torna isso barato é o `@theme` do Tailwind v4 no `app.css` do kit:

```css
@theme {
    --color-background: var(--background);
    /* … */
}
```

Toda cor do Tailwind aponta para uma custom property. Trocar `--primary` troca o app
inteiro, sem tocar em componente nenhum.

## Solução: CSS por atributo, JavaScript só para escrever o atributo

```css
:root[data-crud-palette='azul']      { --primary: …; --ring: …; --sidebar-primary: …; }
:root.dark[data-crud-palette='azul'] { --primary: …; --ring: …; --sidebar-primary: …; }
```

O claro/escuro continua sendo inteiramente da classe `.dark` do kit, e o navegador resolve
a combinação sozinho. **Nada observa aparência, nada reaplica no toggle, nada importa o
`useAppearance`.**

A especificidade não depende da ordem dos arquivos: `:root[data-crud-palette]` vale (0,2,0)
contra os (0,1,0) de `:root` e `.dark`, e `:root.dark[data-crud-palette]` vale (0,3,0).

**A paleta `default` não emite bloco nenhum.** Escolher "Padrão" remove o atributo e valem
as cores do próprio starter kit. O pacote nunca briga com o default do usuário: só
acrescenta alternativas.

### Quais variáveis uma paleta define

O kit continua dono das **superfícies**; a paleta manda só nos **acentos**. Cada bloco
define exatamente estas nove, e nenhuma outra:

```
--primary            --primary-foreground
--ring
--sidebar-primary    --sidebar-primary-foreground
--sidebar-ring
--chart-1  --chart-2  --chart-3
```

Fora da lista ficam `--background`, `--foreground`, `--card`, `--popover`, `--muted`,
`--border`, `--input`, `--secondary`, `--accent` e `--destructive`. O motivo é prático:
superfície e texto são o que faz claro/escuro legível, e o kit já acertou os contrastes
deles nos dois modos. Se a paleta mexesse aí, cada uma das cinco precisaria de uma escala
neutra revisada nos dois modos — dez revisões de contraste em vez de zero — e um erro
apagaria texto na tela do usuário. Trocar acento não tem esse risco.

`--destructive` fica de fora por semântica: vermelho de perigo não muda porque o usuário
gosta de roxo.

### Alternativa descartada: variáveis inline por JavaScript

Um provider fazendo `root.style.setProperty(...)` — o que o sistema atual faz — precisaria
de um `MutationObserver` na classe do `<html>` para saber que o claro/escuro mudou, já que
importar o `useAppearance` do kit é justamente a colisão que se quer evitar. Ganha só em
paleta vinda do backend em runtime, que ninguém pediu. Custa JS em toda página, um observer
para manter e risco de flash a cada toggle.

### Alternativa descartada: variantes no `@theme` do Tailwind

`@theme` não é condicional por seletor, então acabaria precisando das custom properties do
mesmo jeito, com mais mexida no arquivo do usuário.

## Componentes

### No projeto do usuário

**`resources/css/crud-palettes.css`** — cinco paletas (padrão, azul, verde, roxo, vermelho),
dois blocos cada, exceto a padrão, que não emite nenhum. Sem dependência.

**`resources/js/lib/crud-palette.ts`** — a superfície inteira, sem React:

```ts
export const palettes: { id: string; name: string }[];
export function getPalette(): string;
export function setPalette(id: string): void;   // grava no localStorage e aplica o atributo
export function initializeCrudPalette(): void;  // aplica o que estiver salvo
```

Cores não aparecem aqui: elas vivem no CSS. Este arquivo só conhece ids e nomes.

Contrato das quatro funções, para não sobrar decisão para a implementação:

- a chave do `localStorage` é `crud-palette`;
- `getPalette()` devolve `'default'` quando não há nada salvo, e também quando o id salvo
  não está em `palettes` — id órfão, de paleta que o usuário apagou do CSS, não pode deixar
  a tela num estado sem cor definida;
- `setPalette('default')` **remove** o atributo do `<html>` e grava `'default'`;
- `initializeCrudPalette()` é idempotente e pode ser chamada mais de uma vez.

**O seletor** — único arquivo que sabe de framework, e por isso o único que existe em três
versões: `components/crud-palette-selector.tsx`, `.vue` e `.svelte`. O comando instala a
versão da stack detectada, e só ela. As três consomem as mesmas quatro funções acima.

### No pacote

**`src/MarkedRegion.php`** — região marcada em arquivo do usuário, só texto, com `install`,
`replace` e `remove`. Recebe conteúdo, devolve conteúdo novo ou `null` quando não há como
escrever com segurança.

`NavigationRegion` continua existindo e não é refatorada: a âncora dela tem forma de array
("insere antes do primeiro fechamento depois da linha X") e a do `MarkedRegion` tem forma de
elemento ("insere depois desta linha"). Unificar agora seria refatoração sem demanda.

**`src/Console/InstallPaletteCommand.php`** — escreve os três arquivos, faz as três edições,
detecta e oferece limpar o sistema antigo.

**`src/Console/CreatePaletteCommand.php`** — pergunta nome e cor primária em OKLCH,
acrescenta o par de blocos ao `crud-palettes.css` e a entrada em `palettes` do
`crud-palette.ts`, idempotente por id. São dois arquivos a manter em sincronia; é o preço de
as cores morarem no CSS e a lista no TS.

**`src/CrudManager.php`** encolhe para `isPaletteInstalled()`, que confere a existência de
`resources/js/lib/crud-palette.ts`. `getThemes()` sai, e sai junto da facade `Crud`.

## Fluxo

```
boot           app.tsx → initializeCrudPalette() → localStorage → data-crud-palette no <html>
troca          seletor → setPalette('azul') → localStorage + atributo → o CSS reage
claro/escuro   AppearanceTabs do kit → classe .dark no <html> → o CSS reage
```

A terceira linha é o ponto do desenho: **não passa por código do pacote.**

### Persistência: `localStorage`, sem cookie

O kit usa cookie para o dark porque renderiza a classe `.dark` no blade — sem isso a página
pisca de branco para preto. Paleta piscando é variação de cor, não inversão. O flash é
aceito em troca de não tocar no `app.blade.php`. Quem quiser zero flash acrescenta o
atributo no blade à mão; o README documenta como.

`initializeCrudPalette()` entra no `app.tsx`, no mesmo ponto onde o kit já chama
`initializeTheme()` — mesma janela de flash que eles já aceitam para o dark.

## As três edições em arquivo do usuário

| Arquivo | O que entra | Âncora | Idempotência | Se a âncora não existir |
|---|---|---|---|---|
| `resources/css/app.css` | `@import './crud-palettes.css';` | o último `@import` do topo | presença da string | não edita, imprime a linha |
| o arquivo de entrada da stack | o import e a chamada `initializeCrudPalette();` | a chamada `initializeTheme();` | presença da chamada | não edita, imprime as duas linhas |
| a página de aparência da stack | o bloco do seletor entre os marcadores | a linha que renderiza `<AppearanceTabs />` | marcadores presentes → `replace` | não edita, imprime o bloco |

O arquivo de entrada e a página variam por stack (ver a tabela em **Escopo**); as duas
âncoras, não — são a mesma string nos três kits. Os marcadores acompanham a sintaxe de
comentário do arquivo: `{/* crud:palette:start */}` em TSX, `<!-- crud:palette:start -->` em
Vue e Svelte.

Rodar o comando duas vezes tem que produzir arquivos idênticos.

Quando não há como escrever com segurança, o comando **não escreve**, diz qual arquivo ficou
de fora, imprime o trecho para colar à mão e segue para os outros. Termina relatando o que
faltou, em vez de fingir sucesso. É o padrão estabelecido na 3.3.1 para a sidebar.

## Migração de quem tem o sistema antigo

Detecção: existência de `resources/js/lib/themes.ts`.

**Grupo 1 — instalados pelo pacote, oferece apagar:** `lib/themes.ts`,
`components/theme-selector.tsx`, `components/appearance-dropdown.tsx`,
`components/appearance-theme-selector.tsx`, `components/theme-demo.tsx`,
`pages/ThemeExample.tsx`.

**Grupo 2 — do starter kit, sobrescritos pelo pacote:** `hooks/use-appearance.tsx` e
`components/appearance-tabs.tsx`. O comando **não apaga e não tenta restaurar**: imprime o
`git checkout --` de cada um. Restaurar é coisa que só o git do usuário sabe fazer.

Avisa também que a versão antiga acrescentou `@radix-ui/react-tabs` ao `package.json`.

## O que sai na 5.0.0

| Sai | Motivo |
|---|---|
| flag `--theme` | a geração não tem mais relação com paleta |
| prompt "Deseja incluir sistema de temas dinâmicos?" | idem, e leva junto o bug do `$this->options['theme']` |
| `{{themeImports}}`, `{{themeComponents}}` | placeholders são API pública (item 4) |
| `getThemeImports()`, `getThemeComponents()` | sem uso |
| `use-appearance.tsx.stub`, `appearance-tabs.tsx.stub`, `appearance-dropdown.tsx.stub`, `appearance-theme-selector.tsx.stub`, `theme-demo.tsx.stub`, `ThemeExample.tsx.stub`, `theme-selector.tsx.stub`, `themes.ts.stub` | substituídos por três stubs |
| `CrudManager::getThemes()` e o método na facade | não existe mais `themes.ts` para parsear |
| `src/config/themes.php` e o `mergeConfigFrom(..., 'themes')` | a chave global `config('themes')` colide com outros pacotes |

Renomeações, todas breaking e cobertas pelo major:

| Hoje | 5.0.0 |
|---|---|
| `crud:install-theme-system` | `crud:install-palette` |
| `crud:create-theme` | `crud:create-palette` |
| tag de publish `theme-system` | (nenhuma — ver nota abaixo) |
| `config('themes.*')` | `config('crud.palette.*')` |

A tag de publish planejada aqui, `crud-palette`, saiu antes do release: a revisão final
notou que `InstallPaletteCommand` lê os stubs sempre de `__DIR__.'/../stubs/palette/'` e
nunca consulta `crud.stub_path` nem o destino que a tag publicava
(`resources/js/crud-palette/`). Publicar não mudava nada — API pública morta. Sem tag
nenhuma no lugar de `theme-system`.

## Testes

O CI do pacote não tem node, então o eslint e o `tsc` do starter kit continuam fora dele. O
que dá para fixar em PHP:

1. **`MarkedRegionTest`** — texto puro: `install` com âncora presente e ausente, `replace`
   sobre região existente, `remove`, região já instalada, e âncora repetida — que casa na
   **primeira** ocorrência, comportamento definido em vez de acidental. É onde mora o risco
   de corromper arquivo alheio, e é o teste mais denso.
2. **`InstallPaletteCommandTest`** — filesystem temporário: escreve os três arquivos, faz as
   três edições, **roda duas vezes e assevera resultado idêntico**, e com âncora ausente
   assevera que não escreveu e que imprimiu o trecho.
3. **Detecção do sistema antigo** — assevera a separação nos dois grupos e que nenhum arquivo
   do grupo 2 é tocado.
4. **Contrato de lint** — os três seletores entram no `GeneratedLintContractTest` existente:
   ordem dos grupos de import, ordem alfabética, `import type`, chaves no `if`. Os três kits
   impõem as mesmas regras, então o contrato é um só.
5. **Detecção de stack** — assevera que cada arquivo de entrada leva ao seletor certo, e que
   um projeto que não casa com nenhuma das três erra e sai (`FAILURE`, sem escrever nada),
   com mensagem dizendo para passar `--stack=` se for uma delas. A implementação não
   pergunta neste caso — perguntar faria sentido para uma stack **ambígua**, não para uma
   que o projeto simplesmente não tem; a revisão final considerou isso melhor do que o
   texto original desta seção previa.

Verificação manual, obrigatória antes da tag e não automatizável aqui:

- instalar nos três starter kits e conferir limpos o `lint:check` e o checador de tipos de
  cada um (`tsc --noEmit` no react, `vue-tsc --noEmit` no vue, `svelte-check` no svelte);
- trocar de paleta no navegador e alternar claro/escuro, confirmando que as duas dimensões
  são independentes;
- rodar o comando duas vezes e conferir que os arquivos não mudam.

## Escopo

**React, vue e svelte.** Livewire fica para uma spec própria.

Os três primeiros compartilham o mecanismo inteiro, o que foi medido nos starter kits em
30/07/2026: definem o mesmo conjunto de variáveis do shadcn, e as duas âncoras são a **mesma
string** nos três — `<AppearanceTabs />` na página de aparência e `initializeTheme();` no
arquivo de entrada, na linha 31 dos três. Os três também impõem as mesmas regras de lint
(`import/order` alfabético, `consistent-type-imports`, `curly`).

O que muda entre eles é só endereço e linguagem do seletor:

| Stack | Entrada | Página de aparência | Seletor |
|---|---|---|---|
| react | `resources/js/app.tsx` | `pages/settings/appearance.tsx` | `.tsx` |
| vue | `resources/js/app.ts` | `pages/settings/Appearance.vue` | `.vue` |
| svelte | `resources/js/app.ts` | `pages/settings/Appearance.svelte` | `.svelte` |

O `crud-palettes.css` e o `crud-palette.ts` servem os três sem uma linha diferente — o
módulo de ids é TypeScript puro, e vue e svelte importam TS igual.

### Por que livewire fica fora

Não é "mais um seletor": as três dimensões mudam ao mesmo tempo.

- **Variáveis.** O kit não define `--primary` nenhum. Ele tematiza pelo Flux, com
  `--color-accent`, `--color-accent-content` e `--color-accent-foreground`, declaradas em
  `@theme` e sobrescritas num `@layer theme` sob `.dark`. As nove variáveis desta spec não
  existem lá.
- **Seletor.** A página de aparência é um componente Livewire 4 de arquivo único
  (`resources/views/pages/settings/⚡appearance.blade.php`) usando `flux:radio.group` com
  `x-model="$flux.appearance"`. É Blade com Alpine, não componente com estado local.
- **Persistência.** A preferência mora no store do Flux, não no `localStorage` deste pacote.

Enfiar isso aqui significaria dois mecanismos convivendo na mesma release e condicionais de
stack no meio do desenho dos outros três.

## Fora de escopo

- Cookie e renderização do atributo no `app.blade.php`.
- Paleta vinda do backend em runtime.
- Editor visual de paleta. O `crud:create-palette` continua sendo prompt no terminal.
- Migração automática de paleta customizada que o usuário tenha criado no `themes.ts` antigo.
