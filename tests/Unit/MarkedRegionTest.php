<?php

namespace Crud\Tests\Unit;

use Crud\MarkedRegion;
use PHPUnit\Framework\TestCase;

class MarkedRegionTest extends TestCase
{
    private function region(): MarkedRegion
    {
        return new MarkedRegion('{/* crud:palette:start */}', '{/* crud:palette:end */}');
    }

    private function page(): string
    {
        return <<<'TSX'
        export default function Appearance() {
            return (
                <div className="space-y-6">
                    <AppearanceTabs />
                </div>
            );
        }
        TSX;
    }

    public function test_instala_a_regiao_logo_depois_da_ancora(): void
    {
        $result = $this->region()->install($this->page(), '/<AppearanceTabs \/>/', '<CrudPaletteSelector />');

        $this->assertStringContainsString(
            "            <AppearanceTabs />\n"
                . "            {/* crud:palette:start */}\n"
                . "            <CrudPaletteSelector />\n"
                . "            {/* crud:palette:end */}",
            $result
        );
    }

    public function test_sem_ancora_nao_escreve(): void
    {
        $this->assertNull(
            $this->region()->install('<div>nada aqui</div>', '/<AppearanceTabs \/>/', '<X />')
        );
    }

    public function test_regiao_ja_instalada_nao_duplica(): void
    {
        $region = $this->region();
        $once = $region->install($this->page(), '/<AppearanceTabs \/>/', '<CrudPaletteSelector />');

        $this->assertNull($region->install($once, '/<AppearanceTabs \/>/', '<CrudPaletteSelector />'));
    }

    public function test_ancora_repetida_casa_a_primeira(): void
    {
        $content = "<AppearanceTabs />\n<hr />\n<AppearanceTabs />";
        $result = $this->region()->install($content, '/<AppearanceTabs \/>/', '<X />');

        $this->assertSame(
            "<AppearanceTabs />\n{/* crud:palette:start */}\n<X />\n{/* crud:palette:end */}\n<hr />\n<AppearanceTabs />",
            $result
        );
    }

    public function test_replace_troca_so_o_conteudo_da_regiao(): void
    {
        $region = $this->region();
        $installed = $region->install($this->page(), '/<AppearanceTabs \/>/', '<Antigo />');

        $result = $region->replace($installed, '<Novo />');

        $this->assertStringContainsString('<Novo />', $result);
        $this->assertStringNotContainsString('<Antigo />', $result);
        $this->assertStringContainsString('<AppearanceTabs />', $result);
    }

    public function test_replace_sem_regiao_devolve_null(): void
    {
        $this->assertNull($this->region()->replace($this->page(), '<X />'));
    }

    public function test_remove_tira_marcadores_e_conteudo(): void
    {
        $region = $this->region();
        $installed = $region->install($this->page(), '/<AppearanceTabs \/>/', '<X />');

        $result = $region->remove($installed);

        $this->assertStringNotContainsString('crud:palette', $result);
        $this->assertStringNotContainsString('<X />', $result);
        $this->assertStringContainsString('<AppearanceTabs />', $result);
    }

    public function test_remove_sem_regiao_devolve_null(): void
    {
        $this->assertNull($this->region()->remove($this->page()));
    }

    public function test_import_entra_em_ordem_alfabetica_no_grupo(): void
    {
        $content = <<<'TSX'
        import { Head } from '@inertiajs/react';
        import AppearanceTabs from '@/components/appearance-tabs';
        import Heading from '@/components/heading';
        import AppLayout from '@/layouts/app-layout';

        export default function Appearance() {}
        TSX;

        $result = MarkedRegion::insertImport(
            $content,
            "import { CrudPaletteSelector } from '@/components/crud-palette-selector';",
            '@/components/crud-palette-selector'
        );

        $this->assertStringContainsString(
            "import AppearanceTabs from '@/components/appearance-tabs';\n"
                . "import { CrudPaletteSelector } from '@/components/crud-palette-selector';\n"
                . "import Heading from '@/components/heading';",
            $result
        );
    }

    public function test_import_depois_do_ultimo_quando_ordena_por_ultimo(): void
    {
        $content = "import AppLayout from '@/layouts/app-layout';\n\nexport default function A() {}";

        $result = MarkedRegion::insertImport(
            $content,
            "import { initializeCrudPalette } from '@/lib/crud-palette';",
            '@/lib/crud-palette'
        );

        $this->assertStringContainsString(
            "import AppLayout from '@/layouts/app-layout';\n"
                . "import { initializeCrudPalette } from '@/lib/crud-palette';",
            $result
        );
    }

    public function test_import_ja_presente_devolve_o_conteudo_intacto(): void
    {
        $content = "import { initializeCrudPalette } from '@/lib/crud-palette';\n";

        $this->assertSame(
            $content,
            MarkedRegion::insertImport($content, "import { initializeCrudPalette } from '@/lib/crud-palette';", '@/lib/crud-palette')
        );
    }

    public function test_arquivo_sem_import_nenhum_devolve_null(): void
    {
        $this->assertNull(
            MarkedRegion::insertImport('const a = 1;', "import X from '@/x';", '@/x')
        );
    }

    public function test_replace_com_marcadores_na_mesma_linha_devolve_null(): void
    {
        $content = "prefix {/* crud:palette:start */} middle {/* crud:palette:end */} suffix";

        $this->assertNull(
            $this->region()->replace($content, '<Z />')
        );
    }

    public function test_remove_com_marcadores_na_mesma_linha_devolve_null(): void
    {
        $content = "prefix {/* crud:palette:start */} middle {/* crud:palette:end */} suffix";

        $this->assertNull(
            $this->region()->remove($content)
        );
    }

    public function test_casa_a_primeira_ocorrencia_de_marcador_inicial_com_orfao_antes(): void
    {
        $content = "{/* crud:palette:start */}\n"
            . "<AppearanceTabs />\n"
            . "{/* crud:palette:start */}\n"
            . "<Original />\n"
            . "{/* crud:palette:end */}";

        $region = $this->region();
        $result = $region->remove($content);

        // Com guard correto: [0,4], remove tudo da primeira start até o end.
        // Sem guard (bug): reatribui start a cada início, pega [2,4], deixa linhas 0-1.
        $this->assertSame("", $result);
    }

    public function test_install_em_arquivo_crlf_preserva_crlf(): void
    {
        $content = str_replace("\n", "\r\n", $this->page());

        $result = $this->region()->install($content, '/<AppearanceTabs \/>/', '<CrudPaletteSelector />');

        $this->assertNotNull($result);
        $this->assertStringNotContainsString("\r\r\n", $result);
        $this->assertStringContainsString(
            "            <AppearanceTabs />\r\n"
                . "            {/* crud:palette:start */}\r\n"
                . "            <CrudPaletteSelector />\r\n"
                . "            {/* crud:palette:end */}",
            $result
        );
        // Nenhum \n solto: todo fim de linha do resultado é \r\n.
        $this->assertSame(0, preg_match('/(?<!\r)\n/', $result));
    }

    public function test_replace_em_arquivo_crlf_preserva_crlf(): void
    {
        $region = $this->region();
        $installed = $region->install(str_replace("\n", "\r\n", $this->page()), '/<AppearanceTabs \/>/', '<Antigo />');

        $result = $region->replace($installed, '<Novo />');

        $this->assertNotNull($result);
        $this->assertStringContainsString("<Novo />\r\n", $result);
        $this->assertSame(0, preg_match('/(?<!\r)\n/', $result));
    }

    public function test_remove_em_arquivo_crlf_preserva_crlf(): void
    {
        $region = $this->region();
        $installed = $region->install(str_replace("\n", "\r\n", $this->page()), '/<AppearanceTabs \/>/', '<X />');

        $result = $region->remove($installed);

        $this->assertNotNull($result);
        $this->assertStringNotContainsString('crud:palette', $result);
        $this->assertSame(0, preg_match('/(?<!\r)\n/', $result));
    }

    /**
     * Reproduz o achado 2: `MarkedRegion::IMPORT` termina em `;$`, que não casa `;\r`. Num
     * arquivo CRLF, cada "linha" de um `explode("\n", ...)` carrega um `\r` sobrando no
     * fim, e o import nunca casa — `insertImport()` devolvia null mesmo com import de
     * verdade presente, e o chamador desistia de editar o arquivo inteiro.
     */
    public function test_insert_import_em_arquivo_crlf_casa_e_preserva_crlf(): void
    {
        $content = str_replace(
            "\n",
            "\r\n",
            "import AppLayout from '@/layouts/app-layout';\n\nexport default function A() {}"
        );

        $result = MarkedRegion::insertImport(
            $content,
            "import { initializeCrudPalette } from '@/lib/crud-palette';",
            '@/lib/crud-palette'
        );

        $this->assertNotNull($result);
        $this->assertStringContainsString(
            "import AppLayout from '@/layouts/app-layout';\r\n"
                . "import { initializeCrudPalette } from '@/lib/crud-palette';\r\n",
            $result
        );
        $this->assertSame(0, preg_match('/(?<!\r)\n/', $result));
    }
}
