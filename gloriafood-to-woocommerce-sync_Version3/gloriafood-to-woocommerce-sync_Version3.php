<?php
/*
Plugin Name: GloriaFood zu WooCommerce Produktsync (Varianten, Allergene, Addons inkl. globale Gruppen)
Description: Importiert Produkte aus GloriaFood inkl. Größen (Variationen), Allergene & Addons. Unterstützt globale Addon-Gruppen (Kategorie/Top-Level) und berechnet Aufpreise im Warenkorb/Checkout.
Version: 2.4.0
Author: Dominik
*/

if (!defined('ABSPATH')) exit;

// Alle Gruppen optional halten (min=0)
if (!defined('GF_FORCE_OPTIONAL_GROUPS')) define('GF_FORCE_OPTIONAL_GROUPS', true);
// Maximalbegrenzung ignorieren (immer Multi-Choice möglich)
if (!defined('GF_IGNORE_MAX')) define('GF_IGNORE_MAX', true);

/* ---------------------------
   ADMIN MENU
--------------------------- */
add_action('admin_menu', function() {
    add_menu_page('GloriaFood Sync','GloriaFood Sync','manage_woocommerce','gf-sync','gf_wc_sync_page');
});
function gf_wc_sync_page() {
    if (isset($_POST['gf_sync'])) {
        $created = gf_sync_gloriafood_products();
        echo '<div class="updated"><p>' . esc_html($created) . ' Produkte wurden synchronisiert.</p></div>';
    }
    echo '<h1>GloriaFood → WooCommerce Sync</h1>';
    echo '<form method="post"><button class="button button-primary" name="gf_sync">Produkte jetzt synchronisieren</button></form>';
}

/* ---------------------------
   SYNC
--------------------------- */
function gf_sync_gloriafood_products() {
    // WARNUNG: räumt alle Produkte (inkl. Variationen)
    $all = get_posts(['post_type'=>['product','product_variation'],'posts_per_page'=>-1,'post_status'=>'any','fields'=>'ids']);
    foreach ($all as $id) wp_delete_post($id, true);

    $api_key = 'qOm9mIzaRCaMZWXvy7';
    $res = wp_remote_get('https://pos.globalfoodsoft.com/pos/menu', [
        'headers'=>['Authorization'=>$api_key,'Accept'=>'application/json','Glf-Api-Version'=>'2'],
        'timeout'=>25
    ]);
    if (is_wp_error($res)) return 'API-Fehler';
    $data = json_decode(wp_remote_retrieve_body($res), true);
    if (empty($data['categories'])) return 'Keine Kategorien';

    gf_ensure_attribute('Größe','size');
    gf_ensure_attribute('Allergene','allergene');

    $global_groups = gf_normalize_groups(gf_collect_possible_groups($data));
    $count = 0;

    foreach ($data['categories'] as $cat) {
        $term_id = gf_ensure_product_cat($cat);
        $cat_groups = gf_normalize_groups(gf_collect_possible_groups($cat));
        if (empty($cat['items'])) continue;

        foreach ($cat['items'] as $item) {
            $sku = 'gloriafood_'.$item['id'];
            if (gf_wc_product_exists($sku)) continue;

            $allerg = '';
            if (!empty($item['extras']['menu_item_allergens_values'])) {
                $allerg = implode(', ', array_map(fn($a)=>$a['name'],$item['extras']['menu_item_allergens_values']));
            }

            $pid = wp_insert_post([
                'post_title'=>$item['name'],
                'post_content'=>$item['description'] ?? '',
                'post_status'=>'publish','post_type'=>'product'
            ]);
            if (!$pid) continue;

            update_post_meta($pid,'_sku',$sku);
            update_post_meta($pid,'_manage_stock','no');
            update_post_meta($pid,'_stock_status','instock');

            if (!empty($item['image'])) gf_set_product_image($pid,$item['image']);
            if ($term_id) wp_set_object_terms($pid, [(int)$term_id], 'product_cat', false);
            if ($allerg) wp_set_object_terms($pid, $allerg, 'pa_allergene', true);

            // Addon-Schema bauen
            $schema = gf_build_addon_schema_from_item($item,[
                'category_groups'=>$cat_groups,
                'global_groups'=>$global_groups
            ]);

            $has_sizes = !empty($item['sizes']) && is_array($item['sizes']);
            if ($has_sizes) {
                wp_set_object_terms($pid,'variable','product_type');

                $size_term_ids=[]; $size_slugs=[]; $slug_map=[]; $prices=[];
                foreach ($item['sizes'] as $s) {
                    $t = gf_ensure_attribute_term('size',$s['name']);
                    if ($t) { $size_term_ids[]=(int)$t['term_id']; $size_slugs[]=$t['slug']; $slug_map[sanitize_title($s['name'])]=$t['slug']; }
                }
                if ($size_term_ids) wp_set_object_terms($pid,$size_term_ids,'pa_size',false);

                $attrs = [
                    'pa_size'=>['name'=>'pa_size','value'=>implode(' | ', $size_slugs),'position'=>0,'is_visible'=>1,'is_variation'=>1,'is_taxonomy'=>1]
                ];
                if ($allerg) $attrs['pa_allergene']=['name'=>'pa_allergene','value'=>$allerg,'position'=>1,'is_visible'=>1,'is_variation'=>0,'is_taxonomy'=>1];
                update_post_meta($pid,'_product_attributes',$attrs);
                if ($size_slugs) update_post_meta($pid,'_default_attributes',['pa_size'=>$size_slugs[0]]);

                foreach ($item['sizes'] as $s) {
                    $slug_key = sanitize_title($s['name']);
                    $v_slug = $slug_map[$slug_key] ?? sanitize_title($s['name']);
                    $vid = wp_insert_post([
                        'post_title'=>$item['name'].' - '.$s['name'],
                        'post_status'=>'publish','post_parent'=>$pid,'post_type'=>'product_variation',
                        'post_name'=>'product-'.$pid.'-variation-'.$s['id'],'guid'=>home_url('/?product_variation=product-'.$pid.'-variation-'.$s['id'])
                    ]);
                    if ($vid) {
                        $sp = gf_normalize_price($s['price'] ?? 0);
                        $ip = gf_normalize_price($item['price'] ?? 0);
                        $final = $sp>0?$sp:$ip;
                        update_post_meta($vid,'_regular_price',wc_format_decimal($final,2));
                        update_post_meta($vid,'_price',wc_format_decimal($final,2));
                        update_post_meta($vid,'attribute_pa_size',$v_slug);
                        update_post_meta($vid,'_sku',sanitize_title($s['name']));
                        update_post_meta($vid,'_manage_stock','no');
                        update_post_meta($vid,'_stock_status','instock');
                        $prices[]=$final;
                    }
                }
                if ($prices) {
                    $min=min($prices);
                    update_post_meta($pid,'_price',wc_format_decimal($min,2));
                    update_post_meta($pid,'_min_variation_price',wc_format_decimal($min,2));
                    if (function_exists('wc_delete_product_transients')) wc_delete_product_transients($pid);
                }
            } else {
                $base = gf_normalize_price($item['price'] ?? 0);
                update_post_meta($pid,'_regular_price',wc_format_decimal($base,2));
                update_post_meta($pid,'_price',wc_format_decimal($base,2));
                $attrs=[];
                if ($allerg) $attrs['pa_allergene']=['name'=>'pa_allergene','value'=>$allerg,'position'=>0,'is_visible'=>1,'is_variation'=>0,'is_taxonomy'=>1];
                if ($attrs) update_post_meta($pid,'_product_attributes',$attrs);
            }

            if (!empty($schema)) update_post_meta($pid,'_gf_addon_schema',$schema);

            $count++;
        }
    }
    return $count;
}

/* ---------------------------
   SCHEMA
--------------------------- */
function gf_build_addon_schema_from_item($item,$ctx=[]) {
    $schema=['sizes'=>[],'simple_groups'=>[]];
    $cat_groups = isset($ctx['category_groups']) && is_array($ctx['category_groups']) ? $ctx['category_groups'] : [];
    $glob_groups= isset($ctx['global_groups'])   && is_array($ctx['global_groups'])   ? $ctx['global_groups']   : [];

    $item_groups = gf_normalize_groups(gf_collect_possible_groups($item));

    if (!empty($item['sizes']) && is_array($item['sizes'])) {
        foreach ($item['sizes'] as $size) {
            $size_name = trim($size['name'] ?? '');
            if ($size_name==='') continue;

            $size_groups = gf_normalize_groups(gf_collect_possible_groups($size));
            $combined = gf_merge_groups($size_groups,$item_groups);
            $combined = gf_merge_groups($combined,$cat_groups);
            $combined = gf_merge_groups($combined,$glob_groups);

            // Nur Gruppen mit Optionen; "Items" entfernen
            $combined = array_values(array_filter($combined,function($g){
                if (empty($g['options'])) return false;
                $n = isset($g['name']) ? mb_strtolower(trim($g['name'])) : '';
                if (in_array($n,['items','artikel','produkte'],true)) return false;
                return true;
            }));

            $schema['sizes'][]=['name'=>$size_name,'slug'=>sanitize_title($size_name),'groups'=>$combined];
        }
    } else {
        $simple = gf_normalize_groups(gf_collect_possible_groups($item));
        $simple = gf_merge_groups($simple,$cat_groups);
        $simple = gf_merge_groups($simple,$glob_groups);
        $simple = array_values(array_filter($simple,function($g){
            if (empty($g['options'])) return false;
            $n = isset($g['name']) ? mb_strtolower(trim($g['name'])) : '';
            return !in_array($n,['items','artikel','produkte'],true);
        }));
        $schema['simple_groups']=$simple;
    }
    return $schema;
}

function gf_collect_possible_groups($node){
    if(!is_array($node)) return [];
    $groupKeys=['groups','group','global_groups','options_groups','option_groups','choice_groups','extra_groups','toppings_groups','ingredients_groups','addons_groups','crust_groups','edge_groups','subitems_groups'];
    $directOptionKeys=['extras','options','choices','list','values','toppings','ingredients','addons','add_ons','crust','edge','crusts','edges','subitems'];

    $found=[];
    foreach($groupKeys as $k){
        if(!empty($node[$k]) && is_array($node[$k])){
            $arr=array_values(array_filter($node[$k],'is_array'));
            if($arr) $found=array_merge($found,$arr);
        }
    }
    foreach($directOptionKeys as $k){
        if(!empty($node[$k]) && is_array($node[$k])){
            $arr=array_values(array_filter($node[$k],'is_array'));
            if($arr){
                $looks=false; foreach($arr as $el){ if(isset($el['name'])||isset($el['title'])||isset($el['label'])||isset($el['text'])){$looks=true;break;}}
                if($looks){
                    $found[]=['id'=>'wrapped_'.md5($k),'name'=>ucfirst(str_replace(['_','-'],' ',$k)),'min'=>0,'max'=>0,'options'=>$arr,'allow_quantity'=>true];
                }
            }
        }
    }
    return $found;
}

function gf_normalize_groups($raw){
    $groups=[];
    if(empty($raw) || !is_array($raw)) return $groups;
    foreach($raw as $g){
        if(!is_array($g)) continue;
        $gid=(string)gf_first_nonempty($g,['id','group_id','uuid'],uniqid('g_'));
        $gname=(string)gf_first_nonempty($g,['name','title','label'],'Extras');
        $allow_qty=!empty($g['allow_quantity']);

        $force_min = isset($g['force_min'])?(int)$g['force_min']:0;
        $force_max = isset($g['force_max'])?(int)$g['force_max']:0;
        $required  = !empty($g['required']);

        $min=(int)gf_first_nonempty($g,['min','min_selectable','minimum','minRequired','min_required'],0);
        $max=(int)gf_first_nonempty($g,['max','max_selectable','maximum','maxSelectable','max_selectable_count'],0);

        if ($required) $min = max($min,$force_min ?: 1); else $min = max(0,$force_min);
        if ($force_max>0) $max=$force_max;

        if (GF_FORCE_OPTIONAL_GROUPS) $min=0;
        if (GF_IGNORE_MAX) $max=0; // 0 = unbegrenzt → Checkboxen

        $opts_raw = gf_first_nonempty($g,['options','choices','items','list','values'],[]);
        $options=[];
        if(is_array($opts_raw)){
            foreach($opts_raw as $o){
                if(!is_array($o)) continue;
                $oid=(string)gf_first_nonempty($o,['id','option_id','uuid','value','code'],uniqid('o_'));
                $oname=(string)gf_first_nonempty($o,['name','title','label','text'],'');
                if($oname==='') continue;
                $price_raw = gf_first_nonempty($o,['price','amount','value','extra','cost'],0);
                $options[]=['id'=>$oid,'name'=>$oname,'price'=>gf_normalize_price($price_raw)];
            }
        }
        $groups[]=['id'=>$gid,'name'=>$gname,'min'=>max(0,(int)$min),'max'=>max(0,(int)$max),'options'=>$options,'allow_qty'=>$allow_qty];
    }
    return $groups;
}

function gf_merge_groups(array $base,array $extra){
    $idx=[];
    foreach($base as $g){ $idx[$g['id']]=$g; $idx[$g['id']]['_optidx']=[]; foreach($g['options'] as $o){$idx[$g['id']]['_optidx'][$o['id']]=$o;} }
    foreach($extra as $g){
        if(!isset($idx[$g['id']])){
            $g['_optidx']=[]; foreach($g['options'] as $o){$g['_optidx'][$o['id']]=$o;} $idx[$g['id']]=$g;
        }else{
            $t=&$idx[$g['id']];
            if(empty($t['name']) && !empty($g['name'])) $t['name']=$g['name'];
            if((int)$t['min']===0 && (int)$g['min']>0 && !GF_FORCE_OPTIONAL_GROUPS) $t['min']=(int)$g['min'];
            if((int)$t['max']===0 && (int)$g['max']>0 && !GF_IGNORE_MAX) $t['max']=(int)$g['max'];
            foreach($g['options'] as $o){
                if(!isset($t['_optidx'][$o['id']])) $t['_optidx'][$o['id']]=$o;
                else{
                    if(empty($t['_optidx'][$o['id']]['name']) && !empty($o['name'])) $t['_optidx'][$o['id']]['name']=$o['name'];
                    if((float)$t['_optidx'][$o['id']]['price']==0.0 && (float)$o['price']>0.0) $t['_optidx'][$o['id']]['price']=(float)$o['price'];
                }
            }
        }
    }
    $out=[]; foreach($idx as $gid=>$g){ $opts=array_values($g['_optidx']); unset($g['_optidx']); $g['options']=$opts; $out[]=$g; }
    return $out;
}

/* ---------------------------
   FRONTEND RENDER
--------------------------- */
add_action('woocommerce_before_add_to_cart_button','gf_render_addons_ui',15);
function gf_render_addons_ui(){
    global $product; if(!$product) return;
    $schema=get_post_meta($product->get_id(),'_gf_addon_schema',true);
    if(empty($schema)||!is_array($schema)) return;

    wp_nonce_field('gf_addons_nonce','gf_addons_nonce');

    $is_variable=$product->is_type('variable');
    $sizes=$schema['sizes'] ?? [];
    $simple=$schema['simple_groups'] ?? [];

    echo '<div class="gf-addons" style="margin:1rem 0;">';
    echo '<h4 style="margin:0 0 .5rem;">Extras</h4>';

    if($is_variable && $sizes){
        $selected_slug='';
        if(!empty($_REQUEST['attribute_pa_size'])) $selected_slug=sanitize_text_field(wp_unslash($_REQUEST['attribute_pa_size']));
        else{
            if(method_exists($product,'get_default_attributes')){
                $def=$product->get_default_attributes();
                if(!empty($def['pa_size'])) $selected_slug=$def['pa_size'];
            }
        }

        $size_terms = wc_get_product_terms($product->get_id(),'pa_size',['fields'=>'all']);

        foreach($sizes as $size){
            $slug=esc_attr($size['slug']);
            $name=$size['name'] ?? $slug;

            $alt=[]; if($size_terms){
                foreach($size_terms as $t){
                    $tn=mb_strtolower($t->name);
                    if(mb_stripos($tn,mb_strtolower($name))!==false || mb_stripos($tn,mb_strtolower($slug))!==false) $alt[]=$t->slug;
                }
            }
            $alt_attr=esc_attr(implode('|',array_unique($alt)));

            $display='none';
            if($selected_slug){
                if($selected_slug===$slug || in_array($selected_slug,$alt,true)) $display='block';
            } else $display='block';

            echo '<div class="gf-addon-size-block" data-size-slug="'.$slug.'" data-alt-slugs="'.$alt_attr.'" style="display:'.$display.';padding:.5rem 0;">';
            gf_render_groups($size['groups']);
            echo '</div>';
        }
    } else {
        if($simple) gf_render_groups($simple);
        elseif($sizes) gf_render_groups($sizes[0]['groups']);
        else echo '<p style="margin:0;">Keine Extras verfügbar.</p>';
    }
    echo '</div>';
}

/**
 * Immer Checkboxen (Multi-Choice). Preis in data-price, optionale Mengenfelder bei allow_qty.
 */
function gf_render_groups($groups){
    foreach($groups as $group){
        $gid=esc_attr($group['id']);
        $name=esc_html($group['name']);
        $allow_qty = !empty($group['allow_qty']);

        // "Items" ausblenden zur Sicherheit
        $ln=strtolower($name);
        if(in_array($ln,['items','artikel','produkte'],true)) continue;

        echo '<fieldset class="gf-addon-group" style="margin:.5rem 0;padding:.5rem;border:1px solid #eee;border-radius:8px;">';
        echo '<legend style="font-weight:600;">'.$name.'</legend>';

        foreach($group['options'] as $opt){
            $oid=esc_attr($opt['id']);
            $oname=esc_html($opt['name']);
            $price=(float)$opt['price'];
            $price_html = $price>0 ? ' <span class="gf-addon-price" style="margin-left:auto;color:#a23c16;">+'.wc_price($price).'</span>' : '';

            $id = 'gf-addon-'.$gid.'-'.$oid;
            echo '<label for="'.$id.'" class="gf-addon-row" style="display:flex;align-items:center;gap:10px;margin:.25rem 0;padding:.5rem;border:1px solid #e9e9e9;border-radius:6px;">';
            echo '<input type="checkbox" class="gf-addon-check" data-price="'.esc_attr($price).'" name="gf_addons['.$gid.']['.$oid.'][checked]" value="1" id="'.$id.'">';
            echo '<span>'.$oname.'</span>';
            if ($allow_qty) {
                echo '<input type="number" class="gf-addon-qty" name="gf_addons['.$gid.']['.$oid.'][qty]" value="1" min="1" step="1" style="width:72px;margin-left:auto;">';
                echo $price_html;
            } else {
                echo $price_html;
            }
            echo '</label>';
        }

        // Für die (deaktivierte) Validierung noch da – aber wir greifen sie im Validator nicht mehr auf.
        echo '<input type="hidden" name="gf_addons_constraints['.$gid.'][min]" value="0">';
        echo '<input type="hidden" name="gf_addons_constraints['.$gid.'][max]" value="0">';
        echo '</fieldset>';
    }
}

/* ---------------------------
   CART: VALIDIERUNG + PREIS
--------------------------- */
add_filter('woocommerce_add_to_cart_validation','gf_addons_validate',10,3);
function gf_addons_validate($passed,$product_id,$quantity){
    // Wenn kein Nonce → keine Addons
    if (empty($_POST['gf_addons_nonce']) || !wp_verify_nonce($_POST['gf_addons_nonce'],'gf_addons_nonce')) return $passed;

    // Min/Max ignorieren, wenn optional erzwungen/Max ignoriert
    if (GF_FORCE_OPTIONAL_GROUPS && GF_IGNORE_MAX) return $passed;

    // (Fallback: minimale Validierung, falls Konstante geändert wird)
    $constraints = isset($_POST['gf_addons_constraints']) ? (array)$_POST['gf_addons_constraints'] : [];
    $selected = gf_collect_selected_addons_from_post();
    foreach($constraints as $gid=>$rules){
        $min = isset($rules['min'])?(int)$rules['min']:0;
        $max = isset($rules['max'])?(int)$rules['max']:0;
        $count=0;
        if(!empty($selected['multi'][$gid])){
            foreach($selected['multi'][$gid] as $o){ if(!empty($o['checked'])) $count += max(1,(int)($o['qty']??1)); }
        }
        if(!empty($selected['single'][$gid])) $count += 1;

        if($min>0 && $count<$min){ wc_add_notice(__('Bitte wählen Sie mehr Optionen.','gf'),'error'); return false; }
        if($max>0 && $count>$max){ wc_add_notice(__('Zu viele Optionen gewählt.','gf'),'error'); return false; }
    }
    return $passed;
}

add_filter('woocommerce_add_cart_item_data','gf_addons_add_cart_item_data',10,4);
function gf_addons_add_cart_item_data($cart_item_data,$product_id,$variation_id,$quantity){
    if (empty($_POST['gf_addons_nonce']) || !wp_verify_nonce($_POST['gf_addons_nonce'],'gf_addons_nonce')) return $cart_item_data;

    $schema = get_post_meta($product_id,'_gf_addon_schema',true);
    if(empty($schema)) return $cart_item_data;

    // Größe robust aus Variation ableiten
    $size_slug='';
    if ($variation_id){
        $v_attrs = wc_get_product_variation_attributes($variation_id);
        if(!empty($v_attrs['attribute_pa_size'])) $size_slug=$v_attrs['attribute_pa_size'];
    }
    if(!$size_slug && !empty($_POST['attribute_pa_size'])) $size_slug=sanitize_text_field(wp_unslash($_POST['attribute_pa_size']));

    $selected = gf_collect_selected_addons_from_post();
    $addons_detail = gf_build_selected_addons_detail($schema,$selected,$size_slug);
    if($addons_detail){
        $cart_item_data['_gf_addons']=$addons_detail;
        $cart_item_data['unique_key']=md5(maybe_serialize($addons_detail).microtime(true));
    }
    return $cart_item_data;
}

add_action('woocommerce_before_calculate_totals','gf_addons_adjust_price',10,1);
function gf_addons_adjust_price($cart){
    if(is_admin() && !defined('DOING_AJAX')) return;
    foreach($cart->get_cart() as $ci){
        if(!empty($ci['_gf_addons']) && isset($ci['data']) && is_object($ci['data'])){
            $base=(float)$ci['data']->get_price('edit');
            $extra=0.0;
            foreach($ci['_gf_addons'] as $g) $extra += (float)$g['group_total'];
            $ci['data']->set_price(max(0,$base+$extra));
        }
    }
}

add_filter('woocommerce_get_item_data','gf_addons_cart_item_display',10,2);
function gf_addons_cart_item_display($item_data,$cart_item){
    if(empty($cart_item['_gf_addons'])) return $item_data;
    foreach($cart_item['_gf_addons'] as $gname=>$d){
        foreach($d['items'] as $row){
            $label=$gname.': '.$row['name']; if($row['qty']>1) $label.=' x '.(int)$row['qty'];
            $item_data[]=['name'=>$label,'value'=>wc_price($row['price']),'display'=>$label.' – '.wc_price($row['price'])];
        }
    }
    return $item_data;
}
add_action('woocommerce_checkout_create_order_line_item','gf_addons_order_item_meta',10,4);
function gf_addons_order_item_meta($item,$cart_item_key,$values,$order){
    if(empty($values['_gf_addons'])) return;
    foreach($values['_gf_addons'] as $gname=>$d){
        foreach($d['items'] as $row){
            $label=$gname.': '.$row['name']; if($row['qty']>1) $label.=' x '.(int)$row['qty'];
            $item->add_meta_data($label, wc_price($row['price']), true);
        }
    }
}

/* ---------------------------
   HELFER
--------------------------- */
function gf_collect_selected_addons_from_post(){
    $r=['single'=>[],'multi'=>[]];
    if(!empty($_POST['gf_addons_single'])){
        foreach((array)$_POST['gf_addons_single'] as $gid=>$oid){
            $r['single'][sanitize_text_field($gid)] = is_string($oid)?sanitize_text_field($oid):$oid;
        }
    }
    if(!empty($_POST['gf_addons'])){
        foreach((array)$_POST['gf_addons'] as $gid=>$opts){
            $gid=sanitize_text_field($gid); $r['multi'][$gid]=[];
            foreach((array)$opts as $oid=>$data){
                $oid=sanitize_text_field($oid);
                $checked=!empty($data['checked'])?1:0;
                $qty=isset($data['qty'])?max(1,(int)$data['qty']):1;
                if($checked) $r['multi'][$gid][$oid]=['checked'=>1,'qty'=>$qty];
            }
        }
    }
    return $r;
}
function gf_build_selected_addons_detail($schema,$selected,$size_slug=''){
    $groups=[];
    if($size_slug && !empty($schema['sizes'])){
        foreach($schema['sizes'] as $s){
            if(!empty($s['slug']) && $s['slug']===$size_slug){ foreach($s['groups'] as $g) $groups[$g['id']]=$g; break; }
            if(!empty($s['name']) && stripos($size_slug,sanitize_title($s['name']))!==false){ foreach($s['groups'] as $g) $groups[$g['id']]=$g; }
        }
    } else {
        if(!empty($schema['simple_groups'])) foreach($schema['simple_groups'] as $g) $groups[$g['id']]=$g;
        elseif(!empty($schema['sizes'])) foreach($schema['sizes'][0]['groups'] as $g) $groups[$g['id']]=$g;
    }
    if(!$groups) return [];

    $detail=[];
    if(!empty($selected['single'])){
        foreach($selected['single'] as $gid=>$oid){
            if($oid==='' || !isset($groups[$gid])) continue;
            $opt = gf_find_option($groups[$gid],$oid); if(!$opt) continue;
            $e=['name'=>$opt['name'],'qty'=>1,'price'=>(float)$opt['price'],'total'=>(float)$opt['price']];
            $gn=$groups[$gid]['name']; if(!isset($detail[$gn])) $detail[$gn]=['items'=>[],'group_total'=>0];
            $detail[$gn]['items'][]=$e; $detail[$gn]['group_total']+=$e['total'];
        }
    }
    if(!empty($selected['multi'])){
        foreach($selected['multi'] as $gid=>$opts){
            if(!isset($groups[$gid])) continue; $grp=$groups[$gid];
            foreach($opts as $oid=>$st){
                if(empty($st['checked'])) continue;
                $opt=gf_find_option($grp,$oid); if(!$opt) continue;
                $qty=max(1,(int)$st['qty']); $e=['name'=>$opt['name'],'qty'=>$qty,'price'=>(float)$opt['price'],'total'=>(float)$opt['price']*$qty];
                $gn=$grp['name']; if(!isset($detail[$gn])) $detail[$gn]=['items'=>[],'group_total'=>0];
                $detail[$gn]['items'][]=$e; $detail[$gn]['group_total']+=$e['total'];
            }
        }
    }
    return $detail;
}
function gf_find_option($group,$option_id){
    if(empty($group['options'])) return null;
    foreach($group['options'] as $o){ if((string)$o['id']===(string)$option_id) return $o; }
    return null;
}

/* Debug */
add_action('wp_ajax_gf_dump_schema', function(){
    if(!current_user_can('manage_woocommerce')){ status_header(403); wp_die('forbidden'); }
    $pid=isset($_GET['product_id'])?absint($_GET['product_id']):0;
    if(!$pid){ status_header(400); wp_die('missing product_id'); }
    $schema=get_post_meta($pid,'_gf_addon_schema',true);
    header('Content-Type: application/json; charset='.get_bloginfo('charset'));
    echo wp_json_encode($schema, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE); wp_die();
});

/* Utilities */
function gf_wc_product_exists($sku){ return wc_get_product_id_by_sku($sku)?true:false; }
function gf_set_product_image($post_id,$url){
    require_once(ABSPATH.'wp-admin/includes/image.php');
    require_once(ABSPATH.'wp-admin/includes/file.php');
    require_once(ABSPATH.'wp-admin/includes/media.php');
    $tmp=download_url($url); if(is_wp_error($tmp)) return false;
    $arr=['name'=>basename($url),'tmp_name'=>$tmp]; $id=media_handle_sideload($arr,$post_id);
    if(is_wp_error($id)){ @unlink($arr['tmp_name']); return false; }
    set_post_thumbnail($post_id,$id); return true;
}
function gf_ensure_attribute($label,$slug){
    global $wpdb;
    $att=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_name=%s",$slug));
    if(!$att){
        $wpdb->insert("{$wpdb->prefix}woocommerce_attribute_taxonomies",[
            'attribute_label'=>$label,'attribute_name'=>$slug,'attribute_type'=>'select','attribute_orderby'=>'menu_order','attribute_public'=>0
        ]);
        delete_transient('wc_attribute_taxonomies');
    }
    if(!taxonomy_exists('pa_'.$slug)){
        register_taxonomy('pa_'.$slug, ['product'], ['hierarchical'=>true,'show_ui'=>false,'query_var'=>true,'rewrite'=>false]);
    }
    return 'pa_'.$slug;
}
function gf_ensure_attribute_term($attr_slug,$term_name){
    $tax='pa_'.$attr_slug; $slug=sanitize_title($term_name);
    if(!taxonomy_exists($tax)) gf_ensure_attribute(ucfirst($attr_slug),$attr_slug);
    $t=get_term_by('slug',$slug,$tax);
    if(!$t){ $ins=wp_insert_term($term_name,$tax,['slug'=>$slug]); if(is_wp_error($ins)) return null; $t=get_term((int)$ins['term_id'],$tax); }
    if(!$t || is_wp_error($t)) return null;
    return ['term_id'=>(int)$t->term_id,'slug'=>$t->slug];
}
function gf_ensure_product_cat($gf_cat){
    if(empty($gf_cat['id'])||empty($gf_cat['name'])) return 0;
    $slug='gfcat-'.sanitize_title($gf_cat['id']); $name=wp_strip_all_tags($gf_cat['name']);
    $term=get_term_by('slug',$slug,'product_cat'); if(!$term) $term=get_term_by('name',$name,'product_cat');
    if(!$term){ $ins=wp_insert_term($name,'product_cat',['slug'=>$slug]); if(is_wp_error($ins)) return 0; $tid=(int)$ins['term_id']; }
    else { $tid=(int)$term->term_id; if($term->name!==$name) wp_update_term($tid,'product_cat',['name'=>$name]); if($term->slug!==$slug) wp_update_term($tid,'product_cat',['slug'=>$slug]); }
    return $tid;
}
function gf_first_nonempty($arr,$keys,$default=null){ if(!is_array($arr)) return $default; foreach($keys as $k){ if(array_key_exists($k,$arr) && $arr[$k]!==null && $arr[$k]!=='' && $arr[$k]!==[]) return $arr[$k]; } return $default; }
function gf_normalize_price($p){ if($p===null||$p==='') return 0.0; if(is_string($p)) $p=str_replace([' ', ','],['','.' ],$p); $num=(float)$p; $intlike=((string)(int)$p===(string)$p)||((string)(int)$p===(string)floor((float)$p)); if($intlike && $num>=100 && strpos((string)$p,'.')===false) $num=$num/100; return round($num,2); }