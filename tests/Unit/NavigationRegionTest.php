<?php

namespace Crud\Tests\Unit;

use Crud\NavigationRegion;
use PHPUnit\Framework\TestCase;

class NavigationRegionTest extends TestCase
{
    private function region(): NavigationRegion
    {
        return new NavigationRegion('// crud:nav:start', '// crud:nav:end');
    }

    private function sidebarWithEmptyRegion(): string
    {
        return <<<'TSX'
        const mainNavItems: NavItem[] = [
            { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
            // crud:nav:start
            // crud:nav:end
        ];
        TSX;
    }

    public function test_insere_item_em_regiao_vazia(): void
    {
        $result = $this->region()->upsert(
            $this->sidebarWithEmptyRegion(),
            "'/clientes'",
            "{ title: 'Clientes', href: '/clientes', icon: List },"
        );

        $this->assertStringContainsString(
            "    { title: 'Clientes', href: '/clientes', icon: List },",
            $result
        );
        $this->assertStringContainsString('// crud:nav:start', $result);
        $this->assertStringContainsString('// crud:nav:end', $result);
        $this->assertStringContainsString("{ title: 'Dashboard'", $result);
    }

    public function test_substitui_item_com_o_mesmo_href_em_vez_de_duplicar(): void
    {
        $region = $this->region();

        $once = $region->upsert(
            $this->sidebarWithEmptyRegion(),
            "'/clientes'",
            "{ title: 'Clientes', href: '/clientes', icon: List },"
        );

        $twice = $region->upsert(
            $once,
            "'/clientes'",
            "{ title: 'Clientes', href: '/clientes', icon: List },"
        );

        $this->assertSame(1, substr_count($twice, "href: '/clientes'"));
    }

    public function test_acrescenta_segundo_item_sem_apagar_o_primeiro(): void
    {
        $region = $this->region();

        $withFirst = $region->upsert(
            $this->sidebarWithEmptyRegion(),
            "'/clientes'",
            "{ title: 'Clientes', href: '/clientes', icon: List },"
        );

        $withBoth = $region->upsert(
            $withFirst,
            "'/produtos'",
            "{ title: 'Produtos', href: '/produtos', icon: List },"
        );

        $this->assertStringContainsString("href: '/clientes'", $withBoth);
        $this->assertStringContainsString("href: '/produtos'", $withBoth);
    }

    public function test_devolve_null_quando_nao_ha_marcadores(): void
    {
        $content = "const mainNavItems: NavItem[] = [\n];\n";

        $this->assertNull($this->region()->upsert($content, "'/clientes'", 'item'));
    }

    public function test_devolve_null_quando_falta_o_marcador_final(): void
    {
        $content = "const mainNavItems: NavItem[] = [\n    // crud:nav:start\n];\n";

        $this->assertNull($this->region()->upsert($content, "'/clientes'", 'item'));
    }

    public function test_devolve_null_quando_o_marcador_final_vem_antes_do_inicial(): void
    {
        $content = "// crud:nav:end\n// crud:nav:start\n";

        $this->assertNull($this->region()->upsert($content, "'/clientes'", 'item'));
    }

    private const OPEN_PATTERN = '/^const mainNavItems\s*:/';

    private function sidebarWithoutRegion(): string
    {
        return <<<'TSX'
        import { BookOpen, LayoutGrid } from 'lucide-react';
        import type { NavItem } from '@/types';

        const mainNavItems: NavItem[] = [
            { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
        ];
        TSX;
    }

    public function test_install_cria_a_regiao_antes_do_fechamento_do_array(): void
    {
        $result = $this->region()->install(
            $this->sidebarWithoutRegion(),
            self::OPEN_PATTERN,
            "import { List } from 'lucide-react';"
        );

        $this->assertNotNull($result);

        $dashboard = strpos($result, "title: 'Dashboard'");
        $start = strpos($result, '// crud:nav:start');
        $end = strpos($result, '// crud:nav:end');
        $close = strpos($result, '];');

        $this->assertGreaterThan($dashboard, $start, 'a região deve ficar abaixo do Dashboard');
        $this->assertGreaterThan($start, $end);
        $this->assertGreaterThan($end, $close, 'a região deve ficar dentro do array');
    }

    public function test_install_acrescenta_o_import_do_icone(): void
    {
        $result = $this->region()->install(
            $this->sidebarWithoutRegion(),
            self::OPEN_PATTERN,
            "import { List } from 'lucide-react';"
        );

        $this->assertSame(1, substr_count($result, "import { List } from 'lucide-react';"));
    }

    public function test_install_nao_duplica_um_import_ja_presente(): void
    {
        $region = $this->region();
        $import = "import { List } from 'lucide-react';";

        $once = $region->install($this->sidebarWithoutRegion(), self::OPEN_PATTERN, $import);
        $twice = $region->install($once, self::OPEN_PATTERN, $import);

        $this->assertSame(1, substr_count($twice, $import));
    }

    public function test_install_devolve_null_quando_a_ancora_nao_existe(): void
    {
        $content = "const outraCoisa = [\n];\n";

        $result = $this->region()->install(
            $content,
            self::OPEN_PATTERN,
            "import { List } from 'lucide-react';"
        );

        $this->assertNull($result);
    }

    public function test_o_resultado_do_install_aceita_upsert(): void
    {
        $region = $this->region();

        $installed = $region->install(
            $this->sidebarWithoutRegion(),
            self::OPEN_PATTERN,
            "import { List } from 'lucide-react';"
        );

        $result = $region->upsert(
            $installed,
            "'/clientes'",
            "{ title: 'Clientes', href: '/clientes', icon: List },"
        );

        $this->assertNotNull($result);
        $this->assertStringContainsString("href: '/clientes'", $result);
    }
}
