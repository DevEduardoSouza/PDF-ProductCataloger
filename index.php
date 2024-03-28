<?php
/*
Plugin Name: PDF Product Cataloger
Description: Esse plugin tem como função catalogar os produtos em PDF
Version: 1.0.0
Author: Eduardo Souza
*/

require __DIR__.'/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$btn_adicionado = false;

// Função para adicionar um botão na página de administração de produtos
function add_btn_adm_products() {
    global $post_type, $btn_adicionado;

    if ($post_type === 'product' && !$btn_adicionado) {
        $btn_adicionado = true;

        ?>

        <div class="alignleft actions">
            <button type="button" class="button action" id="btn-baixar-pdf">Exportar em PDF</button>
        </div>

        <script>
            document.getElementById('btn-baixar-pdf').addEventListener('click', function() {
                // Bloquear o botão
                document.getElementById('btn-baixar-pdf').disabled = true;

                // Alterar o texto do botão
                document.getElementById('btn-baixar-pdf').textContent = 'Aguarde...';

                // Captura o valor do filtro de categoria de produto
                var categoria = document.getElementById('product_cat').value;

                var stockStatus = document.querySelector('select[name="stock_status"]').value;

                // Redirecionar para a URL da função display_pdf_format com os parâmetros de filtro
                window.location.href = '<?php echo admin_url('admin-ajax.php'); ?>?action=display_pdf_format&product_cat=' + categoria + '&stock_status=' + stockStatus;
            });

        </script>

        <?php
    }
}

add_action('manage_posts_extra_tablenav', 'add_btn_adm_products');

/** 
 *   FUNÇÃO PARA EXIBIR O PDF FORMATADO NO NAVEGADOR
**/
function display_pdf_format() {
    $options = new Options();
    $options->setChroot(__DIR__);
    $options->setIsRemoteEnabled(true);

    $dompdf = new Dompdf($options);
	
	// Array associativo para mapear categorias
$mapeamento_categorias = array(
    'bordado-ingles'   => 'Bordado Inglês',
    'entremeio'        => 'Entremeio',
    'importados'       => 'Importados',
    'mais-vendidas'    => 'Mais Vendidas',
    'renda-de-algodao' => 'Renda de Algodão',
    'renda-guipir'     => 'Renda Guipir',
    'renda-paraiba'    => 'Renda Paraíba',
    'renda-tule'       => 'Renda Tule',
    'sianinha'         => 'Sianinha',
);

    // Capturar os parâmetros do filtro, se estiverem presentes
    $categoria = isset($_GET['product_cat']) ? $_GET['product_cat'] : '';
    $stock_status = isset($_GET['stock_status']) ? $_GET['stock_status'] : '';
	
	$tamanho_papel = ($categoria === 'importados') ? 'A1' : 'A4';

	  // Adicione uma classe específica no corpo do HTML
    $body_class = ($categoria === 'importados') ? 'importados' : '';

    // Se o PDF não estiver em cache, continuar com o processamento

    $args = array(
        'post_type'      => 'product',
        'posts_per_page' => -1,
        'product_cat'    => $categoria,
    );

    // Adicionar o filtro de stock_status, se presente
    if ($stock_status) {
        $args['meta_query'][] = array(
            'key'     => '_stock_status',
            'value'   => $stock_status,
            'compare' => '='
        );
    }

    $query = new WP_Query($args);

    // Carregar o conteúdo HTML do arquivo separado
    $html_template = file_get_contents(__DIR__ . '/product_template.html');

    // Carregar o conteúdo CSS do arquivo separado
    $css = file_get_contents(__DIR__ . '/styles.css');

    // Conteúdo HTML para o PDF
    $html = "<html><head> <meta charset='UTF-8' /> <style>{$css}</style></head><body class='$body_class'>";

   // Verificar se a categoria está no array de mapeamento
$categoria_amigavel = isset($mapeamento_categorias[$categoria]) ? $mapeamento_categorias[$categoria] : $categoria;


     // Adicione a página padrão ao conteúdo HTML (página inicial)
  $html .= '
    <div class="container-init" style="page-break-before: always;">
        <div class="content-init">
            ' . esc_html($categoria_amigavel) . '
        </div>
    </div>
';

    // Adicionar produtos ao conteúdo HTML
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();

            // Obter o código do produto
            $product = wc_get_product(get_the_ID());
            $product_code = $product ? $product->get_sku() : '';

            $product_description = get_post_field('post_excerpt', get_the_ID());
            $product_description = nl2br($product_description); // Adicionar quebras de linha HTML

            // Substituir marcadores de posição pelos valores reais
            $replace = array(
                '{{thumbnail_url}}'       => esc_url(get_the_post_thumbnail_url(get_the_ID(), 'full')),
                '{{product_title}}'       => esc_html(get_the_title()),
                '{{product_code}}'        => esc_html($product_code),
                '{{product_price}}'       => esc_html(number_format(get_post_meta(get_the_ID(), '_regular_price', true), 2, ',', '.')),
                '{{product_attributes}}'  => $product_description, // Usar a breve descrição aqui
            );

            $product_html = str_replace(array_keys($replace), array_values($replace), $html_template);

            $html .= $product_html;
        }
    }

    $html .= '</body></html>';

    $dompdf->loadHtml($html, 'UTF-8');

    // Defina o tamanho do papel e a orientação
    $dompdf->setPaper($tamanho_papel, 'portrait', '5cm');

    // Renderize o HTML para PDF
    $dompdf->render();

    // Obter o conteúdo do PDF renderizado
    $pdf_content = $dompdf->output();


    // Enviar o PDF ao navegador
    header('Content-Type: application/pdf');
    echo $pdf_content;
    exit;
}

add_action('wp_ajax_nopriv_display_pdf_format', 'display_pdf_format');
add_action('wp_ajax_display_pdf_format', 'display_pdf_format');

