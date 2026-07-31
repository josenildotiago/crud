# Onda única de correções da revisão final — relatório

Branch: `palette-marked-region`. Dez commits: nove da onda original (um por achado),
mais um de uma correção adicional levantada pela verificação manual do dono nos três
starter kits, depois da onda. Cada commit tem teste que reproduz o bug contra o código
anterior antes de aplicar a correção. Nenhum `git push`.

## Commits

| # | Achado | Commit |
|---|---|---|
| 1 | Import Svelte sem recuo, bloco errado | `bcbcc90` |
| 2 | CRLF instala pela metade | `c8a5fbe` |
| 3 | `--force` apaga paletas do usuário | `12b0890` |
| 4 | Legado quebra CRUD gerado com `--theme` | `5406f25` |
| 5 | `appearance-dropdown.tsx` no grupo errado | `0ec3b06` |
| 6 | Tag `crud-palette` morta | `2812a06` |
| 7 | Nome sem ASCII gera id vazio | `a7da802` |
| 8 | `addslashes` não escapa quebra de linha real | `c0b321e` |
| 9 | Duas afirmações de documentação erradas | `e378308` |
| 10 | Stubs de seletor estouravam `printWidth: 80` (verificação manual pós-onda) | `12a457e` |

## Verificação final

```
$ vendor/bin/phpunit
OK (161 tests, 785 assertions)

$ vendor/bin/phpstan analyse --no-progress
[OK] No errors

$ composer validate --strict
./composer.json is valid
```

## Achado por achado

**1 — Import Svelte sem recuo, bloco errado (Important).** Confirmado: no
`projeto-exemplo-svelte`, a página tem dois `<script>` (um `module`, com o import de rota
do breadcrumb, também `@/`; um de instância, com os imports de componente). O
`MarkedRegion::insertImport()` varria o arquivo inteiro sem restrição de bloco e sem
recuo — o import do módulo `module` vencia a comparação alfabética antes do laço chegar
aos vizinhos de verdade, e a linha nova entrava em coluna 0, dentro do bloco errado.

Corrigido: `insertImport()` agora restringe a busca ao bloco `<script>` não-`module`
quando o arquivo tem mais de um (`scriptBlockRange()`, `src/MarkedRegion.php`), e a linha
inserida herda o recuo do vizinho que decidiu a posição. Arquivos com zero ou um
`<script>` (`.tsx`, `.vue`) continuam varrendo o arquivo inteiro, sem mudança de
comportamento.

Provado: a fixture `putAppearancePageSvelte()` em `tests/Unit/InstallPaletteCommandTest.php`
foi trocada pela forma real de dois blocos com imports recuados em 4 espaços; a asserção
em `test_svelte_insere_o_seletor_na_pagina_de_aparencia` passou a checar a linha **com**
recuo e a posição dela (depois do fim do primeiro `</script>`). Rodado contra o código
anterior antes da correção: falhou mostrando exatamente o import sem recuo dentro do
bloco `module`.

**2 — CRLF instala pela metade (Important).** Confirmado: `MarkedRegion::IMPORT` termina
em `;$`, que não casa `;\r`. `explode("\n", $content)` num arquivo CRLF deixa um `\r`
sobrando no fim de cada "linha", e o import nunca casava — `insertImport()` devolvia
`null`, e `editAppTsx()`/`editAppearancePage()` desistiam do arquivo inteiro. Mas
`editAppCss()` não passa pela `MarkedRegion()` e escrevia mesmo assim, misturando uma
linha LF solta num arquivo CRLF, sem erro que explicasse.

Corrigido: `MarkedRegion` agora usa `preg_split('/\R/', ...)` (como a `NavigationRegion`
já fazia) em vez de `explode("\n", ...)` em `install()`, `replace()`, `remove()` e
`insertImport()`, detecta se o conteúdo original era CRLF e restaura o fim de linha ao
gravar — no mesmo espírito de como o `CreatePaletteCommand` já preserva CRLF.
`editAppCss()`, que não passa pela `MarkedRegion`, ganhou a mesma tolerância inline. Uma
segunda falha mais estreita apareceu ao provar o teste de integração: o
`str_replace('initializeTheme();', "initializeTheme();\n" . $chamada, ...)` de
`editAppTsx()` grudava um `\n` cru independente do fim de linha do arquivo — corrigido
para escolher `\r\n` ou `\n` a partir do conteúdo que a `MarkedRegion` acabou de devolver.

Provado: casos novos em `MarkedRegionTest` para `install`/`replace`/`remove`/`insertImport`
em conteúdo CRLF (cada um reproduzido como falha contra o código anterior — `install`
produzia fim de linha misturado, `insertImport` devolvia `null`), mais um teste de
integração em `InstallPaletteCommandTest` com projeto inteiro em CRLF, que assevera as
três edições e ausência de qualquer `\n` solto no resultado.

**3 — `--force` apaga paletas do `create-palette` (Important).** Confirmado:
`writeStub()` com `--force` sobrescrevia `crud-palettes.css` e `crud-palette.ts` a partir
do stub sem aviso, levando junto qualquer paleta que `crud:create-palette` tivesse
acrescentado. E o erro de âncora ausente do `CreatePaletteCommand` recomendava
cegamente `crud:install-palette --force` — o comando destrutivo que apagaria a paleta.

Corrigido: `writeStub()` agora extrai os ids de paleta presentes no arquivo atual e no
stub (regex sobre `data-crud-palette='id'` e `id: 'id'`, funciona nos dois formatos sem
saber qual é qual) e, se houver id além dos quatro do stub, avisa quais seriam perdidos
e pede confirmação **mesmo com `--force`** — só a confirmação de sobrescrita comum
respeita `--force`; a de perda de dado, não. A mensagem do `create-palette` foi reescrita
para orientar copiar as paletas para outro lugar antes de decidir, em vez de mandar
`--force` de cara.

Provado: dois casos novos em `InstallPaletteCommandTest` (recusar mantém a paleta nos
dois arquivos; confirmar apaga dos dois) e um em `CreatePaletteCommandTest` para o texto
da nova mensagem — os três reproduzidos como falha contra o código anterior.

**4 — Legado quebra CRUD gerado com `--theme` (Important).** Confirmado: CRUD gerado com
a antiga flag tem `import ThemeSelector from '@/components/theme-selector'` e
`import { useAppearance } from '@/hooks/use-appearance'` materializados nas páginas
Index/Create/Edit/Show; `handleLegacy()` oferece apagar `theme-selector.tsx` sem avisar
disso.

Corrigido: `handleLegacy()` agora avisa, antes da oferta de apagar, que CRUDs gerados com
`--theme` precisam ser regerados (`php artisan getic:install {tabela}`) porque as páginas
ainda importam os componentes que somem. O `CHANGELOG.md` ganhou o mesmo aviso em duas
seções: "Leia antes de atualizar" (parágrafo dedicado) e "Migração" (nota curta apontando
para o parágrafo).

Provado: caso novo em `InstallPaletteCommandTest` checando a linha de aviso (numa
asserção só, porque os dois módulos citados vivem na mesma chamada de `warn()` — duas
`expectsOutputToContain()` para texto da mesma linha colidem no mock de teste do Artisan,
achado à parte durante a prova). Reproduzido como falha contra o `handleLegacy()`
anterior.

**5 — `appearance-dropdown.tsx` no grupo errado (Minor).** Confirmado: estava em
`LEGACY_OURS` (oferece apagar). No starter kit Laravel 12, que o pacote suporta, é
arquivo do kit, importado por `app-header.tsx`.

Corrigido: movido para `LEGACY_THEIRS` (nunca apagado, só recomenda `git checkout --`),
com comentário na constante explicando a razão — a versão antiga sobrescrevia o arquivo
com stub próprio (por isso parecia "nosso"), mas o arquivo em si é do kit.

Provado: caso novo em `InstallPaletteCommandTest` (`putLegacy()` passou a incluir o
arquivo) checando que ele sobrevive à confirmação de limpeza e aparece na lista de
`git checkout --`. Reproduzido como falha contra o agrupamento anterior.

**6 — Tag `crud-palette` morta (Minor).** Confirmado: `CrudServiceProvider` publicava
`src/stubs/palette` para `resources/js/crud-palette/`, mas `InstallPaletteCommand` lê
sempre de `__DIR__.'/../stubs/palette/'` e nunca consulta `crud.stub_path` nem esse
destino.

Corrigido: removida a tag (opção escolhida em vez de fazer o comando honrar
`crud.stub_path`, já que é release major e a tag nunca funcionou em nenhum release —
melhor não nascer do que nascer morta). Atualizados `CLAUDE.md` (lista de API pública),
`CHANGELOG.md` (`Adicionado` e `Migração`) e a spec de design (tabela de renomeações +
nota explicando por que a tag saiu antes do release).

Provado: `tests/Unit/CrudServiceProviderTest.php`, novo, com
`ServiceProvider::pathsToPublish(CrudServiceProvider::class, 'crud-palette')` vazio
(`crud-config` como controle, continua publicando). Reproduzido como falha contra o
provider anterior.

**7 — Nome sem ASCII gera paleta de id vazio (Minor).** Confirmado:
`Str::slug('青い')` devolve `''`; sem guard, o comando criava
`:root[data-crud-palette='']` e `{ id: '', name: '青い' }`.

Corrigido: guard logo após computar `$id` — se vazio, erro explicando para usar nome com
caractere latino, sem escrever em nenhum dos dois arquivos.

Provado: caso novo em `CreatePaletteCommandTest` com nome japonês, checando `exitCode 1`
e os dois arquivos intactos. Reproduzido como falha (`exitCode 0`, escrevia) contra o
código anterior.

**8 — `addslashes` não escapa quebra de linha real (Minor).** Confirmado:
`crud:create-palette "$(printf 'a\nb')"` produzia TypeScript sintaticamente inválido — a
string `name: '...'` terminava sem fechar.

Corrigido: `addcslashes(addslashes($nome), "\n\r")`, extraído para
`escapeNomeParaTS()` e usado nos dois pontos que antes chamavam `addslashes()` sozinho.
A ordem (aspas/barra primeiro, quebra de linha depois) evita reescapar a barra que o
`addcslashes` introduz, porque as duas funções mexem em conjuntos de caracteres
disjuntos.

Provado: caso novo em `CreatePaletteCommandTest` passando nome com `\n` real (via forma
de array do `artisan()`, para não passar pelo parser de string do shell-like input),
checando a sequência de escape de duas letras no TS gerado e ausência de quebra real
dentro da string. Reproduzido como falha (quebra real presente) contra o código anterior.

**9 — Duas afirmações de documentação erradas (Minor).** Confirmado nos dois pontos:
`README.md` dizia que os prompts guiavam por "Nome e identificador da paleta" (não existe
prompt de identificador — sai do `Str::slug`); a spec, no item 5 da seção de testes,
dizia que projeto fora das três stacks "pergunta antes de instalar" (a implementação erra
e sai, sem perguntar).

Corrigido: os dois trechos reescritos para descrever o comportamento real.

Provado: `tests/Unit/DocumentationClaimsTest.php`, novo — greps sobre os dois arquivos
checando ausência do texto antigo e presença do novo. Reproduzido como falha contra o
texto anterior dos dois arquivos.

## Correção adicional (pós-onda) — stubs de seletor estouravam `printWidth: 80`

Levantada pelo dono depois da onda de nove, ao refazer a verificação manual nos três
starter kits restaurados de backup: `npx prettier --check resources/` passou a acusar
`js/components/crud-palette-selector.tsx` (react) e `js/components/CrudPaletteSelector.vue`
(vue) — arquivos que o `crud:install-palette` escreve, não que o usuário editou. O svelte
já saía limpo, confirmando que o achado 1 desta mesma onda funcionou no projeto real. As
seis páginas CRUD que também reprovam o `prettier --check` no react são pendência antiga,
documentada, e não fazem parte desta branch.

Causa: `crud-palette-selector.tsx.stub` tinha a linha do `variant={...}` (o ternário
`selected === palette.id ? 'default' : 'outline'`) e a linha do
`{selected === palette.id && <Check ... />}` acima de 80 colunas;
`CrudPaletteSelector.vue.stub` tinha a linha do `:class="..."` (mesmo ternário, sintaxe
Vue) também acima de 80. Os três starter kits (`react`, `vue`, `svelte`) compartilham
`printWidth: 80`, `tabWidth: 4`, `singleQuote: true` no `.prettierrc` deles.

Corrigido copiando cada stub para um arquivo temporário com a extensão real
(`__crud_fmt_test.tsx` em `projeto-exemplo-react/resources/js/components/`,
`__CrudFmtTest.vue` em `projeto-exemplo-vue/resources/js/components/`), rodando
`npx prettier --write` de dentro do starter kit correspondente — para herdar a config real
dele, não uma suposição — e trazendo o resultado de volta para o stub. Comparado com
`diff` contra o conteúdo anterior: só as duas linhas apontadas mudaram, quebradas
exatamente como o `prettier` do projeto decidiu. Os arquivos temporários foram apagados
depois; os starter kits não são repositórios git aqui (`git status` neles devolve "not a
git repository"), então não há resíduo versionado para limpar.

Confirmado depois, com `npx prettier --check` nos mesmos arquivos temporários: "All
matched files use Prettier code style!" nos dois casos.

Verificado que o contrato de import não mudou: `test_os_seletores_de_paleta_respeitam_o_
contrato_de_lint` (`GeneratedLintContractTest`) continua verde — a mudança foi só dentro
do corpo do JSX/template, os imports não foram tocados.

Provado com teste novo, `test_os_seletores_de_paleta_cabem_em_80_colunas`
(`tests/Unit/GeneratedLintContractTest.php`): percorre as três stubs de seletor e
assevera que nenhuma linha passa de 80 colunas (`mb_strlen`). Reproduzido como falha
contra os stubs anteriores: `crud-palette-selector.tsx.stub: linha 22 passa de 80
colunas` (81 colunas medidas).

Verificação final depois desta correção:

```
$ vendor/bin/phpunit
OK (161 tests, 785 assertions)

$ vendor/bin/phpstan analyse --no-progress
[OK] No errors

$ composer validate --strict
./composer.json is valid
```
