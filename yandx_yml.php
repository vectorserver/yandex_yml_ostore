<?php
require_once 'config.php';

class yandex_yml_ostore
{

    /**
     * @var PDO
     */
    private $db;
    /**
     * @var int|mixed
     */
    private $config_store_id;
    public $site_url = "https://sport-flooring.ru/";
    public $site_name = "sport-flooring.ru";
    public $site_company = "Tne Best inc.";

    private $from_charset = 'utf-8';
    private $eol = "\n";

    public function __construct($cat = 0,$config_store_id=0)
    {
        $this->cat = $cat;
        $this->config_store_id = $config_store_id;
        $this->db = new PDO('mysql:host=' . DB_HOSTNAME . ';dbname=' . DB_DATABASE . '', DB_USERNAME, DB_PASSWORD, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
        $this->categories = array();
        $this->offers = array();

    }

    public function process()
    {
        $this->getCategories($this->cat);

        $this->getProducts($this->categories);

        return $this->getYml();
    }


    public function getProducts($catsDdata)
    {

        foreach ($catsDdata as $cat){


            $query = $this->db->query("SELECT
                    p.`product_id`,
                    p.`status`,
                    p.image,
                    p.model,
                    p.price,
                    d.`name`,
                    d.description,
                    cat.category_id,
                    cat.main_category,
                    p.stock_status_id 
                FROM
                    " . DB_PREFIX . "product AS p
                    INNER JOIN " . DB_PREFIX . "product_to_category AS cat ON p.product_id = cat.product_id
                    INNER JOIN " . DB_PREFIX . "product_description AS d ON p.product_id = d.product_id 
                WHERE
                    p.`status` = 1 
                    AND cat.category_id IN ( ".$cat["id"]." ) 
                GROUP BY
                    p.product_id");

            foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $product){

                if($product['price']){
                    $product["description"] = htmlspecialchars_decode($product["description"]);
                    $product["description"] = str_ireplace(array("&nbsp;","\t","\n","\r"),"",$product["description"]);
                    $product["description"] = strip_tags($product["description"],'');
                    $product["description"] = trim($product["description"]," \t\n\r\0\x0B");
                    $product["description"] = mb_strimwidth($product["description"],0,150,"...");
                    $product['price'] = round($product['price']);
                    $product['image'] = $this->site_url."image/".$product['image'];
                    $product['url'] = $this->site_url."?product_id=".$product['product_id'];

                    $this->offers[] = $product;
                }
                break;
            }
        }
    }

    public function getCategories($parent_id = 0)
    {

        $query = $this->db->query("
                SELECT * FROM " . DB_PREFIX . "category c LEFT JOIN " . DB_PREFIX . "category_description cd ON (c.category_id = cd.category_id) LEFT JOIN " . DB_PREFIX . "category_to_store c2s ON (c.category_id = c2s.category_id) WHERE c.parent_id = '" . (int)$parent_id . "' AND c2s.store_id = '" . (int)$this->config_store_id . "'  AND c.status = '1' ORDER BY c.category_id ASC");

        foreach ($query as $cid=> $tree){
            $this->categories[$cid]['id'] = $tree['category_id'];
            $this->categories[$cid]['parent'] = $tree['parent_id'];
            $this->categories[$cid]['name'] = $tree['name'];

            if($this->getCategories($tree['category_id'])){}

        }
    }

    private function getYml() {
        $yml  = '<?xml version="1.0" encoding="windows-1251"?>' . $this->eol;
        $yml .= '<!DOCTYPE yml_catalog SYSTEM "shops.dtd">' . $this->eol;
        $yml .= '<yml_catalog date="' . date('Y-m-d H:i') . '">' . $this->eol;
        $yml .= '<shop>' . $this->eol;

        // информация о магазине
        $yml .= '<name>'.$this->site_name.'</name>' . $this->eol;
        $yml .= '<company>'.$this->site_company.'</company>' . $this->eol;
        $yml .= '<url>'.$this->site_url.'</url>' . $this->eol;
        $yml .= '<platform>vectorserver.ru</platform>' . $this->eol;

        // валюты
        $yml .= '<currencies>' . $this->eol;
            $yml .= '<currency id="RUR" rate="1" />' . $this->eol;
        $yml .= '</currencies>' . $this->eol;


        // категории
        $yml .= "\t".'<categories>' . $this->eol;
        $yml .= "\t\t".'<category id="'.$this->cat.'">Каталог</category>' . $this->eol;

        foreach ($this->categories as $category) {

            $yml .= "\t\t".'<category id="'.$category["id"].'" parentId="'.$category["parent"].'">'.$category["name"].'</category>' . $this->eol;
        }
        $yml .= "\t".'</categories>' . $this->eol;

        // товарные предложения
        $yml .= "\t".'<offers>' . $this->eol;
        foreach ($this->offers as $offer) {

            $yml .= "\t\t".'<offer id="'.$offer["product_id"].'">' . $this->eol;
            $yml .= "\t\t\t".'<name>'.$offer["name"].'</name>' . $this->eol;
            $yml .= "\t\t\t".'<url>'.$offer["url"].'</url>' . $this->eol;
            $yml .= "\t\t\t".'<price>'.$offer["price"].'</price>' . $this->eol;
            $yml .= "\t\t\t".'<categoryId>'.$offer["category_id"].'</categoryId>' . $this->eol;
            $yml .= "\t\t\t".'<currencyId>RUR</currencyId>' . $this->eol;
            $yml .= "\t\t\t".'<delivery>true</delivery>' . $this->eol;
            $yml .= "\t\t\t".'<description><![CDATA[ '.$offer["description"].' ]]></description>' . $this->eol;

            $yml .= "\t\t".'</offer>' . $this->eol;
        }
        $yml .= "\t".'</offers>' . $this->eol;

        $yml .= '</shop>';
        $yml .= '</yml_catalog>';

        return $yml;
    }
}

$yml = new yandex_yml_ostore(0,0);
echo $yml->process();
