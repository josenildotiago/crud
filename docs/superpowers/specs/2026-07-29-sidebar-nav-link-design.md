# Link de navegação na sidebar para o CRUD gerado

Data: 29/07/2026
Status: aprovado, aguardando plano de implementação

## Problema

O `getic:install` gera Controller, Model, componentes e rotas, mas nada que leve o
usuário até a tela nova. Depois de gerar, a única forma de abrir o CRUD é digitar a URL
à mão — a sidebar do starter kit continua mostrando só o Dashboard.

Isso vale para as cinco stacks. Nenhum stub do pacote toca em navegação hoje.

## Restrição que define o desenho

A sidebar é um arquivo do usuário, que ele edita e customiza. O pacote nunca sobrescreve
arquivo do usuário sem `confirm()` ou `--force`, e cada stack guarda a navegação num
formato diferente:

| Stack | Arquivo | Formato |
|---|---|---|
| react | `resources/js/components/app-sidebar.tsx` | `const mainNavItems: NavItem[] = [...]` |
| vue | `resources/js/components/AppSidebar.vue` | SFC |
| svelte | `resources/js/components/AppSidebar.svelte` | SFC |
| livewire | `resources/views/layouts/app/sidebar.blade.php` | Flux navlist |
| blade | a definir quando a stack for implementada | — |

Escrever um parser por formato seria frágil e caro. A alternativa escolhida trata os
cinco casos como texto.

## Solução: região delimitada por comentários

O pacote gerencia apenas o trecho entre dois marcadores, e nada fora deles.

```tsx
const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    // crud:nav:start
    { title: 'Clientes', href: '/clientes', icon: List },
    { title: 'Produtos', href: '/produtos', icon: List },
    // crud:nav:end
];
```

O comentário acompanha a linguagem do arquivo: `//` em TSX e Svelte, `{{-- --}}` no Blade.

### Por que `href` é string literal

O item podia usar a rota tipada do wayfinder (`href: clientesIndex()`), mas isso exigiria
um `import` no topo do arquivo — **fora** da região gerenciada. O pacote passaria a
manter duas regiões no mesmo arquivo, dobrando a superfície de edição e o risco.

Com `href: '/clientes'` o pacote gerencia um bloco só e nunca toca nos imports. A perda é
restrita ao item de menu; as páginas geradas continuam usando wayfinder normalmente. O
tipo `NavItem.href` é `NonNullable<InertiaLinkProps['href']>`, que aceita string.

O ícone tem o mesmo problema e a mesma solução: um único ícone fixo (`List` do lucide),
importado uma vez no setup, compartilhado por todos os CRUDs. Ícone por CRUD exigiria
gerenciar imports e não foi pedido.

## Fluxo

Roda depois da geração dos componentes, dentro do fluxo da stack. Três casos:

1. **Arquivo de sidebar não existe** — `warning()` em português, imprime o snippet para
   colar à mão, segue. Menu ausente nunca aborta a geração do CRUD.
2. **Existe, sem marcadores** — `confirm('Adicionar o link na sidebar?')`. Aceitando,
   insere o import do ícone e o par de marcadores já com o item dentro. Recusando,
   imprime o snippet e segue.
3. **Existe, com marcadores** — escreve só entre eles.

### Onde os marcadores entram, no caso 2

Esse é o único momento em que o pacote escreve fora de uma região que ele já controla, e
por isso precisa de uma âncora. Cada stack define a sua — na react, a linha que abre o
array de navegação principal (`const mainNavItems: NavItem[] = [`). Os marcadores entram
imediatamente **antes do fechamento** desse array, de modo que os CRUDs apareçam abaixo
do Dashboard e de qualquer outro item que o usuário já tenha.

A âncora é procurada uma única vez, no setup. **Se não for encontrada** — arquivo
reestruturado, array renomeado, navegação movida para outro lugar — o pacote emite
`warning()`, imprime o snippet e **não escreve nada**. Falhar em achar a âncora é seguro
por construção: o pior caso é o usuário colar o bloco à mão.

Deliberadamente não ancoramos no item "Dashboard" em si: ele é um objeto multilinha, o
usuário pode tê-lo renomeado ou removido, e casar o fim de um literal de objeto por
expressão regular é o tipo de parsing que este desenho existe para evitar.

## Idempotência

A chave é o `href`. Ao inserir, se já houver item com aquele `href` na região, ele é
**substituído**; caso contrário o novo item é acrescentado ao fim da região. Gerar
`clientes` três vezes deixa uma linha.

Mesma disciplina de `registerTypeInBarrel()` e do `require` idempotente que
`buildRouter()` acrescenta ao `web.php`.

## Marcador malformado

Se encontrar só um dos dois marcadores, ou `end` antes de `start`: `warning()` e **não
escreve nada**. Deixar o arquivo pela metade é pior que o link faltando.

## Configuração

Chave nova em `src/config/crud.php`:

```php
'navigation' => [
    // Insere um link para o CRUD gerado na sidebar do projeto.
    'sidebar' => true,
],
```

Lida sempre com default — `config('crud.navigation.sidebar', true)` — porque
`mergeConfigFrom()` faz merge raso e um array publicado pelo usuário substitui o bloco
inteiro.

Em `false`, o pacote nunca olha para a sidebar. Recusar o `confirm` pula apenas aquela
execução: não gravamos marcador de "desativado" num arquivo que o usuário acabou de pedir
para não tocar.

## Escopo

**Agora:** apenas a stack `react`, a única que gera componentes funcionando.

**Depois:** as demais stacks recebem sua variante junto com a implementação do respectivo
builder. O mecanismo de marcador é o mesmo texto puro nos cinco casos; muda só o arquivo
alvo, a sintaxe do comentário e o formato de uma linha de item.

## Testes

A manipulação de marcadores é texto puro: não depende de MySQL nem de introspecção de
schema, e por isso é testável no Testbench, ao contrário do resto da geração.

Quatro casos, sobre um arquivo temporário:

1. arquivo sem marcadores, âncora presente → insere marcadores e item antes do fechamento
   do array, e adiciona o import do ícone
2. marcadores presentes e vazios → insere o item entre eles
3. item com o mesmo `href` já presente → substitui, não duplica
4. marcador malformado (só `start`, ou `end` antes de `start`) → não altera o arquivo
5. arquivo sem marcadores e sem âncora reconhecível → não altera o arquivo

## Fora de escopo

- Ícone configurável por CRUD.
- Remover o item quando o usuário apaga o CRUD à mão.
- Agrupar os CRUDs em submenu.
- Ordenação dos itens além da ordem de inserção.
