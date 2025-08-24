# Sistema de Temas Dinâmicos

Este sistema permite alternar entre diferentes temas de cores em tempo real, além do modo claro/escuro tradicional.

## Como Funciona

### 1. Definição de Temas

Os temas são definidos em `/resources/js/lib/themes.ts`. Cada tema contém:

- `id`: Identificador único
- `name`: Nome exibido ao usuário
- `description`: Descrição opcional
- `variables`: Objeto com as variáveis CSS para modo claro e escuro

### 2. Hook de Aparência

O hook `useAppearance()` gerencia tanto o modo (light/dark/system) quanto o tema atual:

```tsx
const { appearance, themeId, updateAppearance, updateTheme } = useAppearance();
```

### 3. Componentes Disponíveis

#### AppearanceToggleDropdown

Dropdown completo que combina seleção de modo e tema:

```tsx
import AppearanceToggleDropdown from '@/components/appearance-dropdown';

<AppearanceToggleDropdown />;
```

#### ThemeSelector

Seletor específico para temas:

```tsx
import ThemeSelector from '@/components/theme-selector';

<ThemeSelector />;
```

#### AppearanceAndThemeSelector

Versão expandida com submenu:

```tsx
import AppearanceAndThemeSelector from '@/components/appearance-theme-selector';

<AppearanceAndThemeSelector />;
```

#### AppearanceTabs

Interface com tabs para configurações (usado na página de configurações):

```tsx
import AppearanceTabs from '@/components/appearance-tabs';

<AppearanceTabs />;
```

## Adicionando Novos Temas

Para adicionar um novo tema, edite `/resources/js/lib/themes.ts`:

```typescript
{
    id: 'meu-tema',
    name: 'Meu Tema',
    description: 'Descrição do meu tema',
    variables: {
        light: {
            '--primary': 'oklch(0.5 0.2 200)', // Azul
            '--secondary': 'oklch(0.9 0.05 200)',
            // ... outras variáveis
        },
        dark: {
            '--primary': 'oklch(0.7 0.15 200)',
            '--secondary': 'oklch(0.25 0.05 200)',
            // ... outras variáveis
        }
    }
}
```

### Variáveis Obrigatórias

Cada tema deve definir todas essas variáveis:

- `--background`, `--foreground`
- `--card`, `--card-foreground`
- `--popover`, `--popover-foreground`
- `--primary`, `--primary-foreground`
- `--secondary`, `--secondary-foreground`
- `--muted`, `--muted-foreground`
- `--accent`, `--accent-foreground`
- `--destructive`, `--destructive-foreground`
- `--border`, `--input`, `--ring`
- `--chart-1` até `--chart-5`
- `--sidebar`, `--sidebar-foreground`, `--sidebar-primary`, `--sidebar-primary-foreground`
- `--sidebar-accent`, `--sidebar-accent-foreground`, `--sidebar-border`, `--sidebar-ring`

## Uso Programático

### Alternar Modo

```tsx
updateAppearance('light'); // ou 'dark' ou 'system'
```

### Alternar Tema

```tsx
updateTheme('blue'); // ID do tema
```

### Obter Tema Atual

```tsx
import { getTheme } from '@/lib/themes';

const currentTheme = getTheme(themeId);
```

## Persistência

As preferências são salvas automaticamente em:

- `localStorage` para persistência no lado do cliente
- Cookies para suporte ao SSR

## Cores OKLCH

O sistema usa cores no formato OKLCH para melhor consistência visual:

- `oklch(lightness chroma hue)`
- Lightness: 0-1 (0=preto, 1=branco)
- Chroma: 0-0.4 (saturação)
- Hue: 0-360 (tom)

### Exemplo de Paleta

```css
--primary: oklch(0.55 0.2 260); /* Azul médio */
--secondary: oklch(0.92 0.05 260); /* Azul muito claro */
--accent: oklch(0.88 0.08 260); /* Azul claro */
```

## Componente de Demonstração

Para testar os temas, use o componente `ThemeDemo`:

```tsx
import ThemeDemo from '@/components/theme-demo';

<ThemeDemo />;
```
