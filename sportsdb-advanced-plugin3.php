<?php
/*
Plugin Name: SportsDB Championships Plugin
Description: Exibe tabelas de classificação, próximos jogos, últimos jogos, resultados, ranking de jogadores e comparação de times com dados da API TheSportsDB.
Version: 7.6
Author: Albluipp
Text Domain: sportsdb-championships-plugin
*/

if ( ! defined( 'ABSPATH' ) ) exit;

// 1. CONFIGURAÇÕES BÁSICAS
function sportsdb_get_championships() {
    return [
        'Série A' => 4351,
        'Série B' => 4404,
        'Série C' => 4625,
        'Série D' => 5079,
        'Copa do Brasil' => 4355,
        'Copa Libertadores' => 4346,
        'Premier League' => 4328,
        'La Liga' => 4335,
        'Bundesliga' => 4331,
        'Ligue 1' => 4334,
    ];
}
function sportsdb_fetch_data($endpoint, $cache_duration = 900, $force_update = false) {
    $api_key = get_option('sportsdb_championships_api_key', '3');
    $url = "https://www.thesportsdb.com/api/v1/json/{$api_key}/{$endpoint}";
    $transient_key = 'sportsdb_' . md5($url);
    $last_update = get_transient($transient_key . '_timestamp');
    if (!$force_update) {
        $data = get_transient($transient_key);
        if ($data !== false) return $data;
    }
    $response = wp_remote_get($url);
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) != 200) {
        if (isset($data) && $data !== false) {
            $GLOBALS['sportsdb_fallback_notice'] = 'Mostrando dados em modo offline/cache. Última atualização: ' .
                ($last_update ? date_i18n('d/m/Y H:i', $last_update) : 'desconhecida');
            return $data;
        }
        return null;
    }
    $data = json_decode(wp_remote_retrieve_body($response), true);
    set_transient($transient_key, $data, $cache_duration);
    set_transient($transient_key . '_timestamp', time(), $cache_duration);
    return $data;
}

// 2. MENUS ADMIN
add_action('admin_menu', function() {
    add_menu_page(
        'SportsDB Campeonatos',
        'SportsDB',
        'manage_options',
        'sportsdb-shortcodes',
        'sportsdb_admin_page',
        'dashicons-awards',
        6
    );
    add_submenu_page(
        'sportsdb-shortcodes',
        'Atualizar Dados SportsDB',
        'Atualizar Dados',
        'manage_options',
        'sportsdb-refresh',
        'sportsdb_refresh_page'
    );
    add_submenu_page(
        'sportsdb-shortcodes',
        'Limpar Cache SportsDB',
        'Limpar Cache',
        'manage_options',
        'sportsdb-clear-cache',
        'sportsdb_clear_cache_page'
    );
    add_submenu_page(
        'sportsdb-shortcodes',
        'Visual',
        'Visual',
        'manage_options',
        'sportsdb-visual',
        'sportsdb_customize_page'
    );
});

function sportsdb_admin_page() {
    $championships = sportsdb_get_championships();
    ?>
    <div class="wrap">
        <h1>SportsDB - Shortcodes Disponíveis</h1>
        <p>
            <a href="<?php echo admin_url('admin.php?page=sportsdb-refresh'); ?>" class="button button-secondary">Atualizar Dados Manualmente</a>
            <a href="<?php echo admin_url('admin.php?page=sportsdb-clear-cache'); ?>" class="button">Limpar Cache</a>
            <a href="<?php echo admin_url('admin.php?page=sportsdb-visual'); ?>" class="button">Visual</a>
        </p>
        <table class="widefat striped" aria-label="Tabela de Shortcodes de Campeonatos">
            <thead>
                <tr>
                    <th>Campeonato</th>
                    <th>Tabela Completa</th>
                    <th>Top 5</th>
                    <th>Próximos Jogos</th>
                    <th>Últimos Jogos</th>
                    <th>Resultados</th>
                    <th>Jogos Time*</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $championships as $name => $id ) : $slug = sanitize_title($name); ?>
                    <tr>
                        <td><?php echo esc_html($name); ?></td>
                        <td><code>[sportsdb_table_<?php echo $slug; ?>_all]</code></td>
                        <td><code>[sportsdb_table_<?php echo $slug; ?>_top5]</code></td>
                        <td><code>[sportsdb_next_<?php echo $slug; ?>]</code></td>
                        <td><code>[sportsdb_last_<?php echo $slug; ?>]</code></td>
                        <td><code>[sportsdb_results_<?php echo $slug; ?>]</code></td>
                        <td><code>[sportsdb_team_matches_<?php echo $slug; ?> team="NOME DO TIME"]</code></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <small>*Exemplo: [sportsdb_team_matches_serie-a team="Flamengo"]</small>
        <br><br>
        <h2>Ranking de Jogadores / Comparação de Times</h2>
        <ul>
            <li><b>Ranking de Artilharia:</b> <code>[sportsdb_ranking league="Série A" season="2024" type="goals" limit="10"]</code></li>
            <li><b>Ranking de Amarelos:</b> <code>[sportsdb_ranking league="Série A" season="2024" type="yellow" limit="10"]</code></li>
            <li><b>Ranking de Vermelhos:</b> <code>[sportsdb_ranking league="Série A" season="2024" type="red" limit="10"]</code></li>
            <li><b>Comparação de times:</b> <code>[sportsdb_compare league="Série A" season="2024" team1="Flamengo" team2="Palmeiras"]</code></li>
        </ul>
    </div>
    <?php
}
function sportsdb_refresh_page() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die('Acesso negado.');
    $championships = sportsdb_get_championships();
    $msg = '';
    if(isset($_POST['sportsdb_refresh']) && check_admin_referer('sportsdb_refresh_action', 'sportsdb_refresh_nonce')){
        $campeonato = sanitize_text_field($_POST['campeonato']);
        $season = sanitize_text_field($_POST['season']);
        $type = sanitize_text_field($_POST['type']);
        $id = isset($championships[$campeonato]) ? $championships[$campeonato] : 4351;
        if($type === 'table') sportsdb_fetch_data("lookuptable.php?l={$id}&s={$season}", 900, true);
        else sportsdb_fetch_data("eventsseason.php?id={$id}&s={$season}", 900, true);
        $msg = "Atualizado!";
    }
    ?>
    <div class="wrap">
    <h1>Atualizar Dados Manualmente</h1>
    <?php if ($msg): ?><div class="notice notice-success"><p><?php echo $msg; ?></p></div><?php endif; ?>
    <form method="post">
        <?php wp_nonce_field('sportsdb_refresh_action', 'sportsdb_refresh_nonce'); ?>
        <table class="form-table">
            <tr>
                <th>Campeonato</th>
                <td>
                    <select name="campeonato">
                        <?php foreach($championships as $name=>$id): ?>
                        <option value="<?php echo esc_attr($name); ?>"><?php echo esc_html($name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th>Season</th>
                <td><input type="text" name="season" value="<?php echo esc_attr(date('Y')); ?>" /></td>
            </tr>
            <tr>
                <th>Tipo</th>
                <td>
                    <select name="type">
                        <option value="table">Tabela</option>
                        <option value="matches">Jogos</option>
                    </select>
                </td>
            </tr>
        </table>
        <?php submit_button('Atualizar Agora','primary','sportsdb_refresh'); ?>
    </form>
    </div>
    <?php
}
function sportsdb_clear_cache_page() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die('Acesso negado.');
    $msg = '';
    if(isset($_POST['sportsdb_clear_cache']) && check_admin_referer('sportsdb_clear_cache_action', 'sportsdb_clear_cache_nonce')){
        global $wpdb;
        $wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE 'sportsdb_%'" );
        $msg = "Cache limpo!";
    }
    ?>
    <div class="wrap">
        <h1>Limpar Cache SportsDB</h1>
        <?php if ($msg): ?><div class="notice notice-success"><p><?php echo $msg; ?></p></div><?php endif; ?>
        <form method="post">
            <?php wp_nonce_field('sportsdb_clear_cache_action', 'sportsdb_clear_cache_nonce'); ?>
            <p>Esta ação apaga todos os dados em cache do plugin.</p>
            <?php submit_button('Limpar Cache Agora', 'primary', 'sportsdb_clear_cache'); ?>
        </form>
    </div>
    <?php
}
function sportsdb_customize_page() { ?>
    <div class="wrap">
        <h1>Opções Visuais do SportsDB</h1>
        <form method="post" action="options.php">
            <?php settings_fields( 'sportsdb_options_group' ); ?>
            <table class="form-table">
                <tr>
                    <th>Cor Primária</th>
                    <td><input type="color" name="sportsdb_primary_color" value="<?php echo esc_attr( get_option('sportsdb_primary_color', '#1a237e') ); ?>" /></td>
                </tr>
                <tr>
                    <th>Cor Secundária</th>
                    <td><input type="color" name="sportsdb_secondary_color" value="<?php echo esc_attr( get_option('sportsdb_secondary_color', '#f5f7fa') ); ?>" /></td>
                </tr>
                <tr>
                    <th>Fonte</th>
                    <td><input type="text" name="sportsdb_font_family" value="<?php echo esc_attr( get_option('sportsdb_font_family', 'Inter, Arial, sans-serif') ); ?>" /></td>
                </tr>
                <tr>
                    <th>CSS extra</th>
                    <td><textarea name="sportsdb_custom_css" rows="7" cols="60" style="font-family:monospace;"><?php echo esc_textarea( get_option('sportsdb_custom_css', '') ); ?></textarea></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
<?php }
add_action( 'admin_init', function(){
    register_setting( 'sportsdb_options_group', 'sportsdb_primary_color' );
    register_setting( 'sportsdb_options_group', 'sportsdb_secondary_color' );
    register_setting( 'sportsdb_options_group', 'sportsdb_font_family' );
    register_setting( 'sportsdb_options_group', 'sportsdb_custom_css' );
});

// VISUAL/CSS
function sportsdb_custom_css() {
    $primary = get_option('sportsdb_primary_color', '#1a237e');
    $secondary = get_option('sportsdb_secondary_color', '#f5f7fa');
    $font = get_option('sportsdb_font_family', 'Inter, Arial, sans-serif');
    $extra = get_option('sportsdb_custom_css', '');
    ob_start();
    ?>
    :root {
        --sportsdb-primary: <?php echo esc_attr($primary); ?>;
        --sportsdb-secondary: <?php echo esc_attr($secondary); ?>;
        --sportsdb-font: <?php echo esc_attr($font); ?>;
        --sportsdb-radius: 16px;
        --sportsdb-shadow: 0 4px 16px 0 rgba(0,0,0,0.09);
        --sportsdb-accent: #00bcd4;
        --sportsdb-positive: #43a047;
        --sportsdb-negative: #e53935;
        --sportsdb-neutral: #757575;
    }
    <?php echo $extra; ?>
    .sportsdb-table-wrapper, .sportsdb-matches-wrapper, .sportsdb-ranking-wrapper, .sportsdb-compare-wrapper {
        width:100%; overflow-x:auto; margin:18px 0; background: #fff;
        border-radius:var(--sportsdb-radius,10px); box-shadow:var(--sportsdb-shadow,0 2px 12px 0 rgba(0,0,0,0.12));
        padding:16px 5px 12px 5px;
        scrollbar-width: thin;
    }
    .sportsdb-table, .sportsdb-matches-table, .sportsdb-ranking-table, .sportsdb-compare-table {
        width:100%; border-collapse:separate; border-spacing:0;
        font-family: var(--sportsdb-font, Arial, sans-serif); background: #fff;
        border-radius:var(--sportsdb-radius,10px); overflow:hidden;
    }
    .sportsdb-table th, .sportsdb-table td,
    .sportsdb-matches-table th, .sportsdb-matches-table td,
    .sportsdb-ranking-table th, .sportsdb-ranking-table td,
    .sportsdb-compare-table th, .sportsdb-compare-table td {
        border: 1px solid #e3e3e3;
        padding: 13px 7px;
        text-align: center;
        font-size: 1.1em;
        line-height: 1.4;
    }
    .sportsdb-table th, .sportsdb-matches-table th, .sportsdb-ranking-table th, .sportsdb-compare-table th {
        background: var(--sportsdb-primary, #1a237e);
        color: #fff;
        letter-spacing: .06em;
        font-weight: 700;
        border-bottom: 0;
    }
    .sportsdb-table .team-cell, .sportsdb-matches-table .team-cell, .sportsdb-ranking-table .player-cell, .sportsdb-compare-table .team-cell {
        display: flex;
        align-items: center;
        gap: 10px;
        white-space: nowrap;
        overflow: hidden;
    }
    .sportsdb-table .team-cell img,
    .sportsdb-matches-table .team-cell img,
    .sportsdb-ranking-table .player-cell img,
    .sportsdb-compare-table .team-cell img {
        width: 28px; height: 28px; border-radius:50%; background:#f9f9f9; object-fit:cover; box-shadow:0 1px 6px rgba(0,0,0,0.08); flex-shrink:0;
    }
    .sportsdb-table td.team-column,
    .sportsdb-compare-table td.team-column {
        text-align: left; max-width:180px;
    }
    .sportsdb-matches-table td.team-column, 
    .sportsdb-matches-table th.team-column {
        text-align: center !important; max-width:180px;
    }
    .sportsdb-matches-table .team-column .team-cell {
        justify-content: center !important;
    }
    .sportsdb-table tbody tr:nth-child(even),
    .sportsdb-matches-table tbody tr:nth-child(even),
    .sportsdb-ranking-table tbody tr:nth-child(even),
    .sportsdb-compare-table tbody tr:nth-child(even) {
        background-color: var(--sportsdb-secondary, #f5f7fa);
    }
    .sportsdb-table tbody tr:hover,
    .sportsdb-matches-table tbody tr:hover,
    .sportsdb-ranking-table tbody tr:hover,
    .sportsdb-compare-table tbody tr:hover {
        background:#e3f2fd;
        transition:.15s;
    }
    .sportsdb-warning { font-size: 13px; }
    .sportsdb-ranking-flag { width:22px; height:16px; border-radius:2px; vertical-align:middle; margin-right:5px; object-fit:cover; }
    .sportsdb-compare-btn {
        background:var(--sportsdb-accent,#00bcd4); color:#fff; border:none; border-radius:6px; padding:7px 18px; font-size:1em; cursor:pointer;
        margin-top:10px; font-weight:600; letter-spacing:.04em; transition:.2s;
    }
    .sportsdb-compare-btn:hover { opacity:.93; filter:brightness(1.13); }
    @media (max-width:1100px) { .sportsdb-table th, .sportsdb-table td, .sportsdb-matches-table th, .sportsdb-matches-table td, .sportsdb-ranking-table th, .sportsdb-ranking-table td, .sportsdb-compare-table th, .sportsdb-compare-table td { font-size: 1em; padding: 10px 4px; } }
    @media (max-width:800px) { .sportsdb-table th, .sportsdb-table td, .sportsdb-matches-table th, .sportsdb-matches-table td, .sportsdb-ranking-table th, .sportsdb-ranking-table td, .sportsdb-compare-table th, .sportsdb-compare-table td { font-size: 0.97em; padding: 7px 3px; } }
    @media (max-width:600px) {
        .sportsdb-table, .sportsdb-matches-table, .sportsdb-ranking-table, .sportsdb-compare-table { font-size:0.92em; }
        .sportsdb-table-wrapper, .sportsdb-matches-wrapper, .sportsdb-ranking-wrapper, .sportsdb-compare-wrapper { padding:6px 1px; }
        .sportsdb-table th, .sportsdb-table td, .sportsdb-matches-table th, .sportsdb-matches-table td, .sportsdb-ranking-table th, .sportsdb-ranking-table td, .sportsdb-compare-table th, .sportsdb-compare-table td { font-size: 0.90em; padding: 6px 2px; }
        .sportsdb-table .team-cell img, .sportsdb-matches-table .team-cell img, .sportsdb-ranking-table .player-cell img, .sportsdb-compare-table .team-cell img { width:18px;height:18px;}
        .sportsdb-ranking-flag { width:16px; height:12px;}
    }
    @media (max-width:410px) {
        .sportsdb-table th, .sportsdb-table td, .sportsdb-matches-table th, .sportsdb-matches-table td, .sportsdb-ranking-table th, .sportsdb-ranking-table td, .sportsdb-compare-table th, .sportsdb-compare-table td { font-size:0.82em; padding: 4px 1px; }
        .sportsdb-table .team-cell img, .sportsdb-matches-table .team-cell img, .sportsdb-ranking-table .player-cell img, .sportsdb-compare-table .team-cell img { width:13px;height:13px;}
        .sportsdb-ranking-flag { width:11px; height:8px;}
    }
    <?php
    return ob_get_clean();
}
function sportsdb_fallback_notice_html() {
    if (!empty($GLOBALS['sportsdb_fallback_notice'])) {
        return '<div class="sportsdb-warning" style="background:#fff3cd;border:1px solid #ffeeba;color:#856404;padding:8px 16px;margin:10px 0;border-radius:4px" role="status">'
            . esc_html($GLOBALS['sportsdb_fallback_notice']) . '</div>';
    }
    return '';
}

// AJAX + WRAPPER
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_script( 'sportsdb-ajax', plugins_url( 'sportsdb-ajax.js', __FILE__ ), [ 'jquery' ], null, true );
    wp_localize_script( 'sportsdb-ajax', 'sportsdbAjax', [
        'ajaxurl' => admin_url( 'admin-ajax.php' ),
        'security' => wp_create_nonce( 'sportsdb_nonce' ),
        'spinner_url' => includes_url('images/spinner.gif')
    ]);
});
if(!function_exists('sportsdb_ajax_shortcode_wrap')) {
    function sportsdb_ajax_shortcode_wrap($atts, $shortcode_name) {
        $data = '';
        foreach ($atts as $k => $v) {
            $data .= ' data-' . esc_attr($k) . '="' . esc_attr($v) . '"';
        }
        return '<div class="sportsdb-ajax-shortcode" data-shortcode="' . esc_attr($shortcode_name) . '"' . $data . ' aria-busy="true" aria-live="polite" tabindex="0">Carregando...</div>';
    }
}
add_action('wp_ajax_nopriv_sportsdb_shortcode', 'sportsdb_ajax_shortcode_callback');
add_action('wp_ajax_sportsdb_shortcode', 'sportsdb_ajax_shortcode_callback');
function sportsdb_ajax_shortcode_callback() {
    check_ajax_referer( 'sportsdb_nonce', 'security' );
    $shortcode = sanitize_text_field( $_POST['shortcode'] ?? '' );
    $atts = isset($_POST['atts']) ? (array) $_POST['atts'] : [];
    if ( !preg_match( '/^sportsdb_/', $shortcode ) && !in_array($shortcode, ['sportsdb_ranking','sportsdb_compare']) ) {
        wp_send_json_error( 'Shortcode inválido.' );
    }
    $result = do_shortcode( '[' . $shortcode .
        ( !empty($atts) ? ' ' . http_build_query($atts, '', ' ') : '' ) .
        ']' );
    wp_send_json_success( $result );
}

// SHORTCODES PADRÕES (Tabela, Jogos, etc)
function sportsdb_generate_table($data, $limit = null, $compact = false, $slug = '') {
    if ( empty( $data['table'] ) ) return '<p>Tabela não encontrada.</p>';
    $teams = $limit ? array_slice( $data['table'], 0, $limit ) : $data['table'];
    $css = sportsdb_custom_css();
    ob_start();
    ?>
<style><?php echo $css; ?></style>
<div class="sportsdb-table-wrapper <?php echo esc_attr($slug); ?>">
    <?php echo sportsdb_fallback_notice_html(); ?>
    <table class="sportsdb-table" aria-label="Tabela de classificação do campeonato">
        <thead>
            <tr>
                <th>Posição</th>
                <th>Time</th>
                <th>Pontos</th>
                <?php if(!$compact): ?>
                    <th>Jogos</th>
                    <th>Vitórias</th>
                    <th>Empates</th>
                    <th>Derrotas</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach($teams as $team): ?>
            <tr>
                <td><?php echo esc_html($team['intRank']); ?></td>
                <td class="team-column"><div class="team-cell">
                    <?php if(!empty($team['strBadge'])): ?>
                        <img src="<?php echo esc_url($team['strBadge']); ?>" alt="Escudo do <?php echo esc_attr($team['strTeam']); ?>" loading="lazy">
                    <?php endif; ?>
                    <?php echo esc_html($team['strTeam']); ?>
                </div></td>
                <td><?php echo esc_html($team['intPoints']); ?></td>
                <?php if(!$compact): ?>
                    <td><?php echo esc_html($team['intPlayed']); ?></td>
                    <td><?php echo esc_html($team['intWin']); ?></td>
                    <td><?php echo esc_html($team['intDraw']); ?></td>
                    <td><?php echo esc_html($team['intLoss']); ?></td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
    <?php
    return ob_get_clean();
}
function sportsdb_generate_table_shortcode($name, $id, $limit = null, $compact = false) {
    $slug = sanitize_title($name) . ($limit ? '_top5' : '_all');
    $shortcode_name = "sportsdb_table_{$slug}";
    return function($atts = []) use ($id, $limit, $compact, $slug, $shortcode_name) {
        $atts = shortcode_atts(['season' => date('Y')], $atts);
        if (defined('DOING_AJAX') && DOING_AJAX) {
            $data = sportsdb_fetch_data("lookuptable.php?l={$id}&s={$atts['season']}");
            return sportsdb_generate_table($data, $limit, $compact, "sportsdb_table_{$slug}");
        }
        add_action('wp_footer', function() { wp_enqueue_script('sportsdb-ajax'); });
        return sportsdb_ajax_shortcode_wrap($atts, $shortcode_name);
    };
}
function sportsdb_generate_matches_shortcode($id, $type, $slug) {
    $shortcode_name = "sportsdb_{$type}_{$slug}";
    return function($atts = []) use ($id, $type, $slug, $shortcode_name) {
        $atts = shortcode_atts(['season' => date('Y')], $atts);
        if (defined('DOING_AJAX') && DOING_AJAX) {
            $data = sportsdb_fetch_data("eventsseason.php?id={$id}&s={$atts['season']}");
            if (empty($data['events'])) return '<p>Não há partidas disponíveis.</p>';
            $events = $data['events'];
            usort($events, fn($a, $b) => strtotime($b['dateEvent'] . ' ' . $b['strTime']) <=> strtotime($a['dateEvent'] . ' ' . $a['strTime']));
            $now = time();
            $filtered = array_filter($events, function($event) use ($type, $now) {
                $timestamp = strtotime($event['dateEvent'] . ' ' . $event['strTime']);
                return ($type === 'next' && $timestamp > $now) || ($type === 'last' && $timestamp < $now) || $type === 'all';
            });
            if ($type === 'last') $filtered = array_slice($filtered, 0, 5);
            elseif ($type === 'next') $filtered = array_slice(array_reverse($filtered), 0, 5);
            else $filtered = array_slice($filtered, 0, 5);
            $css = sportsdb_custom_css();
            ob_start();
            ?>
<style><?php echo $css; ?></style>
<div class="sportsdb-matches-wrapper <?php echo esc_attr($slug); ?>">
    <?php echo sportsdb_fallback_notice_html(); ?>
    <table class="sportsdb-matches-table" aria-label="Jogos do campeonato">
        <thead>
            <tr>
                <?php if($type==='next'): ?>
                    <th class="team-column">Mandante</th>
                    <th class="team-column">Visitante</th>
                    <th>Data</th>
                    <th>Hora</th>
                <?php else: ?>
                    <th class="team-column">Mandante</th>
                    <th>Placar</th>
                    <th class="team-column">Visitante</th>
                    <th>Data</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach($filtered as $m): ?>
            <tr>
                <?php if($type==='next'): ?>
                    <td class="team-column"><div class="team-cell">
                        <?php if(!empty($m['strHomeTeamBadge'])) echo '<img src="'.esc_url($m['strHomeTeamBadge']).'" alt="Escudo do '.esc_attr($m['strHomeTeam']).'" loading="lazy">'; ?>
                        <?php echo esc_html($m['strHomeTeam']); ?>
                    </div></td>
                    <td class="team-column"><div class="team-cell">
                        <?php if(!empty($m['strAwayTeamBadge'])) echo '<img src="'.esc_url($m['strAwayTeamBadge']).'" alt="Escudo do '.esc_attr($m['strAwayTeam']).'" loading="lazy">'; ?>
                        <?php echo esc_html($m['strAwayTeam']); ?>
                    </div></td>
                    <td><?php echo !empty($m['dateEvent']) ? date('d/m', strtotime($m['dateEvent'])) : '-'; ?></td>
                    <td><?php echo !empty($m['strTime']) && $m['strTime']!=='00:00:00'?date('H:i',strtotime($m['strTime'])):'-'; ?></td>
                <?php else: ?>
                    <td class="team-column"><div class="team-cell">
                        <?php if(!empty($m['strHomeTeamBadge'])) echo '<img src="'.esc_url($m['strHomeTeamBadge']).'" alt="Escudo do '.esc_attr($m['strHomeTeam']).'" loading="lazy">'; ?>
                        <?php echo esc_html($m['strHomeTeam']); ?>
                    </div></td>
                    <td><?php echo (isset($m['intHomeScore']) && isset($m['intAwayScore'])) ? esc_html($m['intHomeScore']).' x '.esc_html($m['intAwayScore']) : 'x'; ?></td>
                    <td class="team-column"><div class="team-cell">
                        <?php if(!empty($m['strAwayTeamBadge'])) echo '<img src="'.esc_url($m['strAwayTeamBadge']).'" alt="Escudo do '.esc_attr($m['strAwayTeam']).'" loading="lazy">'; ?>
                        <?php echo esc_html($m['strAwayTeam']); ?>
                    </div></td>
                    <td><?php echo !empty($m['dateEvent']) ? date('d/m', strtotime($m['dateEvent'])) : '-'; ?></td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
            <?php
            return ob_get_clean();
        }
        add_action('wp_footer', function(){ wp_enqueue_script('sportsdb-ajax'); });
        return sportsdb_ajax_shortcode_wrap($atts, $shortcode_name);
    };
}
function sportsdb_team_matches_shortcode($championship_id, $slug) {
    $shortcode_name = "sportsdb_team_matches_{$slug}";
    return function($atts = []) use ($championship_id, $slug, $shortcode_name) {
        $atts = shortcode_atts( [ 'team' => '', 'season' => date('Y'), 'history' => '5' ], $atts );
        $team = trim($atts['team']);
        if (!$team) return '<p>Informe o nome do time no parâmetro <code>team</code>.</p>';
        $data = sportsdb_fetch_data("eventsseason.php?id={$championship_id}&s={$atts['season']}");
        if (empty($data['events'])) return '<p>Não há partidas disponíveis.</p>';
        $matches = array_filter($data['events'], function($match) use ($team) {
            return stripos($match['strHomeTeam'],$team)!==false || stripos($match['strAwayTeam'],$team)!==false;
        });
        usort($matches, fn($a, $b) => strtotime($b['dateEvent'] . ' ' . $b['strTime']) <=> strtotime($a['dateEvent'] . ' ' . $a['strTime']));
        $history = intval($atts['history']);
        $matches = array_slice($matches, 0, $history>0?$history:5);
        $stats = [ 'jogos'=>0, 'vitorias'=>0, 'empates'=>0, 'derrotas'=>0, 'gols_pro'=>0, 'gols_contra'=>0 ];
        foreach($matches as $m) {
            $stats['jogos']++;
            $is_home = (stripos($m['strHomeTeam'],$team)!==false);
            $gol_pro = $is_home ? intval($m['intHomeScore']) : intval($m['intAwayScore']);
            $gol_contra = $is_home ? intval($m['intAwayScore']) : intval($m['intHomeScore']);
            $stats['gols_pro'] += $gol_pro; $stats['gols_contra'] += $gol_contra;
            if ($gol_pro>$gol_contra) $stats['vitorias']++;
            elseif ($gol_pro==$gol_contra) $stats['empates']++;
            else $stats['derrotas']++;
        }
        $css = sportsdb_custom_css();
        ob_start();
        ?>
<style><?php echo $css; ?></style>
<div class="sportsdb-table-wrapper" tabindex="0" role="region" aria-label="Jogos recentes do time <?php echo esc_attr($team); ?>">
    <h3 tabindex="0">Estatísticas dos últimos <?php echo count($matches); ?> jogos de <span style="color:var(--sportsdb-primary)"><?php echo esc_html($team); ?></span></h3>
    <ul style="display:flex;flex-wrap:wrap;gap:20px;list-style:none;padding:0;margin-bottom:8px;">
        <li><strong>Jogos:</strong> <?php echo $stats['jogos']; ?></li>
        <li><strong>Vitórias:</strong> <?php echo $stats['vitorias']; ?></li>
        <li><strong>Empates:</strong> <?php echo $stats['empates']; ?></li>
        <li><strong>Derrotas:</strong> <?php echo $stats['derrotas']; ?></li>
        <li><strong>Gols Pró:</strong> <?php echo $stats['gols_pro']; ?></li>
        <li><strong>Gols Contra:</strong> <?php echo $stats['gols_contra']; ?></li>
        <li><strong>Saldo:</strong> <?php echo $stats['gols_pro']-$stats['gols_contra']; ?></li>
    </ul>
    <table class="sportsdb-matches-table" aria-label="Jogos do time <?php echo esc_attr($team); ?>">
        <thead>
            <tr>
                <th class="team-column">Mandante</th>
                <th>Placar</th>
                <th class="team-column">Visitante</th>
                <th>Data</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($matches as $m): ?>
            <tr>
                <td class="team-column"><div class="team-cell">
                    <?php if(!empty($m['strHomeTeamBadge'])) echo '<img src="'.esc_url($m['strHomeTeamBadge']).'" alt="Escudo do '.esc_attr($m['strHomeTeam']).'" loading="lazy">'; ?>
                    <?php echo esc_html($m['strHomeTeam']); ?>
                </div></td>
                <td>
                    <?php
                    if (isset($m['intHomeScore']) && isset($m['intAwayScore']) && $m['intHomeScore']!==null && $m['intAwayScore']!==null) {
                        echo esc_html($m['intHomeScore']).' x '.esc_html($m['intAwayScore']);
                    } else { echo 'x'; }
                    ?>
                </td>
                <td class="team-column"><div class="team-cell">
                    <?php if(!empty($m['strAwayTeamBadge'])) echo '<img src="'.esc_url($m['strAwayTeamBadge']).'" alt="Escudo do '.esc_attr($m['strAwayTeam']).'" loading="lazy">'; ?>
                    <?php echo esc_html($m['strAwayTeam']); ?>
                </div></td>
                <td><?php echo !empty($m['dateEvent']) ? date('d/m', strtotime($m['dateEvent'])) : '-'; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
        <?php
        return ob_get_clean();
    };
}

// RANKING DE JOGADORES/COMPARAÇÃO
function sportsdb_ranking_shortcode($atts) {
    $atts = shortcode_atts([
        'league' => '',
        'season' => '',
        'type'   => 'goals',
        'limit'  => 20
    ], $atts);
    $championships = sportsdb_get_championships();
    $league_id = '';
    foreach($championships as $name=>$id) {
        if (sanitize_title($name) == sanitize_title($atts['league'])) {
            $league_id = $id;
            break;
        }
    }
    if (!$league_id) return '<p>Campeonato inválido.</p>';
    $season = $atts['season'] ? $atts['season'] : date('Y');
    $data = sportsdb_fetch_data("lookuptopscorers.php?l={$league_id}&s={$season}");
    if (empty($data['topscorers'])) return '<p>Ranking indisponível para este campeonato.</p>';
    $type = $atts['type'];
    $limit = intval($atts['limit']);
    $players = array_slice($data['topscorers'], 0, $limit);
    $css = sportsdb_custom_css();
    ob_start();
    ?>
<style><?php echo $css; ?></style>
<div class="sportsdb-ranking-wrapper" tabindex="0" role="region" aria-label="Ranking de Jogadores">
    <h3 style="margin:0 0 12px 8px;">
        <?php
        if($type === 'goals') echo '<span style="color:var(--sportsdb-accent)">Artilharia</span>';
        elseif($type === 'yellow') echo '<span style="color:var(--sportsdb-neutral)">Cartões Amarelos</span>';
        elseif($type === 'red') echo '<span style="color:var(--sportsdb-negative)">Cartões Vermelhos</span>';
        ?>
    </h3>
    <table class="sportsdb-ranking-table" aria-label="Ranking dos jogadores">
        <thead>
            <tr>
                <th>#</th>
                <th>Jogador</th>
                <th>Time</th>
                <?php if($type==='goals'): ?>
                    <th>Gols</th>
                <?php elseif($type==='yellow'): ?>
                    <th>Amarelos</th>
                <?php elseif($type==='red'): ?>
                    <th>Vermelhos</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach($players as $i=>$pl): ?>
            <tr>
                <td><?php echo $i+1; ?></td>
                <td class="player-cell">
                    <?php if(!empty($pl['strPlayerCutout'])): ?>
                        <img src="<?php echo esc_url($pl['strPlayerCutout']); ?>" alt="<?php echo esc_attr($pl['strPlayer']); ?>" loading="lazy">
                    <?php endif; ?>
                    <?php echo esc_html($pl['strPlayer']); ?>
                    <?php if(!empty($pl['strNationality'])): ?>
                        <img src="https://flagcdn.com/24x18/<?php echo strtolower(substr($pl['strNationality'],0,2)); ?>.png"
                             class="sportsdb-ranking-flag" alt="<?php echo esc_attr($pl['strNationality']); ?>"
                             title="<?php echo esc_attr($pl['strNationality']); ?>">
                    <?php endif; ?>
                </td>
                <td><?php echo esc_html($pl['strTeam']); ?></td>
                <?php if($type==='goals'): ?>
                    <td style="color:var(--sportsdb-positive);font-weight:600;"><?php echo (int)$pl['intGoals']; ?></td>
                <?php elseif($type==='yellow'): ?>
                    <td style="color:var(--sportsdb-neutral);font-weight:600;"><?php echo (int)$pl['intYellowCards']; ?></td>
                <?php elseif($type==='red'): ?>
                    <td style="color:var(--sportsdb-negative);font-weight:600;"><?php echo (int)$pl['intRedCards']; ?></td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
    <?php
    return ob_get_clean();
}
add_shortcode('sportsdb_ranking', 'sportsdb_ranking_shortcode');

function sportsdb_compare_teams_shortcode($atts) {
    $atts = shortcode_atts([
        'league' => '',
        'season' => '',
        'team1'  => '',
        'team2'  => ''
    ], $atts);
    $championships = sportsdb_get_championships();
    $league_id = '';
    foreach($championships as $name=>$id) {
        if (sanitize_title($name) == sanitize_title($atts['league'])) {
            $league_id = $id;
            break;
        }
    }
    if (!$league_id || !$atts['team1'] || !$atts['team2']) return '<p>Parâmetros inválidos para comparação.</p>';
    $season = $atts['season'] ? $atts['season'] : date('Y');
    $data = sportsdb_fetch_data("eventsseason.php?id={$league_id}&s={$season}");
    if (empty($data['events'])) return '<p>Dados de jogos indisponíveis para este campeonato.</p>';
    $team1 = trim($atts['team1']);
    $team2 = trim($atts['team2']);
    $matches = array_filter($data['events'], function($m) use ($team1, $team2){
        return (stripos($m['strHomeTeam'],$team1)!==false && stripos($m['strAwayTeam'],$team2)!==false) ||
               (stripos($m['strHomeTeam'],$team2)!==false && stripos($m['strAwayTeam'],$team1)!==false);
    });
    $stats1 = $stats2 = ['jogos'=>0,'v'=>0,'e'=>0,'d'=>0,'g_pro'=>0,'g_contra'=>0];
    foreach($matches as $m) {
        if(!isset($m['intHomeScore']) || !isset($m['intAwayScore'])) continue;
        $is1home = (stripos($m['strHomeTeam'],$team1)!==false);
        $t1g = $is1home ? (int)$m['intHomeScore'] : (int)$m['intAwayScore'];
        $t2g = $is1home ? (int)$m['intAwayScore'] : (int)$m['intHomeScore'];
        $stats1['jogos']++; $stats1['g_pro']+=$t1g; $stats1['g_contra']+=$t2g;
        $stats2['jogos']++; $stats2['g_pro']+=$t2g; $stats2['g_contra']+=$t1g;
        if($t1g>$t2g) { $stats1['v']++; $stats2['d']++; }
        elseif($t1g==$t2g) { $stats1['e']++; $stats2['e']++; }
        else { $stats1['d']++; $stats2['v']++; }
    }
    $css = sportsdb_custom_css();
    ob_start();
    ?>
<style><?php echo $css; ?></style>
<div class="sportsdb-compare-wrapper" tabindex="0" role="region" aria-label="Comparação de Times">
    <h3 style="margin:0 0 12px 8px;">
        <span style="color:var(--sportsdb-accent)">Comparativo entre <?php echo esc_html($team1); ?> e <?php echo esc_html($team2); ?></span>
    </h3>
    <table class="sportsdb-compare-table" aria-label="Histórico dos confrontos">
        <thead>
            <tr>
                <th>Time</th>
                <th>Jogos</th>
                <th>Vitórias</th>
                <th>Empates</th>
                <th>Derrotas</th>
                <th>Gols Pró</th>
                <th>Gols Contra</th>
                <th>Saldo</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="team-column"><span style="font-weight:600;"><?php echo esc_html($team1); ?></span></td>
                <td><?php echo $stats1['jogos']; ?></td>
                <td style="color:var(--sportsdb-positive)"><?php echo $stats1['v']; ?></td>
                <td><?php echo $stats1['e']; ?></td>
                <td style="color:var(--sportsdb-negative)"><?php echo $stats1['d']; ?></td>
                <td><?php echo $stats1['g_pro']; ?></td>
                <td><?php echo $stats1['g_contra']; ?></td>
                <td><?php echo $stats1['g_pro']-$stats1['g_contra']; ?></td>
            </tr>
            <tr>
                <td class="team-column"><span style="font-weight:600;"><?php echo esc_html($team2); ?></span></td>
                <td><?php echo $stats2['jogos']; ?></td>
                <td style="color:var(--sportsdb-positive)"><?php echo $stats2['v']; ?></td>
                <td><?php echo $stats2['e']; ?></td>
                <td style="color:var(--sportsdb-negative)"><?php echo $stats2['d']; ?></td>
                <td><?php echo $stats2['g_pro']; ?></td>
                <td><?php echo $stats2['g_contra']; ?></td>
                <td><?php echo $stats2['g_pro']-$stats2['g_contra']; ?></td>
            </tr>
        </tbody>
    </table>
    <?php if(count($matches)): ?>
    <h4 style="margin:20px 0 6px 0;">Últimos confrontos</h4>
    <table class="sportsdb-compare-table" aria-label="Resultados dos confrontos">
        <thead>
            <tr>
                <th>Mandante</th>
                <th>Placar</th>
                <th>Visitante</th>
                <th>Data</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach($matches as $m): ?>
            <tr>
                <td class="team-column"><div class="team-cell">
                    <?php echo esc_html($m['strHomeTeam']); ?>
                </div></td>
                <td><?php echo $m['intHomeScore'].' x '.$m['intAwayScore']; ?></td>
                <td class="team-column"><div class="team-cell">
                    <?php echo esc_html($m['strAwayTeam']); ?>
                </div></td>
                <td><?php echo !empty($m['dateEvent']) ? date('d/m/Y', strtotime($m['dateEvent'])) : '-'; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
    <?php
    return ob_get_clean();
}
add_shortcode('sportsdb_compare', 'sportsdb_compare_teams_shortcode');

// REGISTRO DOS SHORTCODES PADRÕES
function sportsdb_register_championship_shortcodes() {
    $championships = sportsdb_get_championships();
    foreach ( $championships as $name => $id ) {
        $slug = sanitize_title( $name );
        add_shortcode( "sportsdb_table_{$slug}_all", sportsdb_generate_table_shortcode( $name, $id, null, false ) );
        add_shortcode( "sportsdb_table_{$slug}_top5", sportsdb_generate_table_shortcode( $name, $id, 5, true ) );
        add_shortcode( "sportsdb_next_{$slug}", sportsdb_generate_matches_shortcode( $id, 'next', "{$slug}" ) );
        add_shortcode( "sportsdb_last_{$slug}", sportsdb_generate_matches_shortcode( $id, 'last', "{$slug}" ) );
        add_shortcode( "sportsdb_results_{$slug}", sportsdb_generate_matches_shortcode( $id, 'all', "{$slug}" ) );
        add_shortcode( "sportsdb_team_matches_{$slug}", sportsdb_team_matches_shortcode( $id, "{$slug}" ) );
    }
}
add_action( 'init', 'sportsdb_register_championship_shortcodes' );
?>