<?php
/**
 * FecShop file.
 *
 * @link http://www.fecshop.com/
 * @copyright Copyright (c) 2016 FecShop Software LLC
 * @license http://www.fecshop.com/license/
 */
namespace fecshop\app\appapi\modules\V1\controllers;

use fecshop\app\appapi\modules\AppapiTokenController;
use Yii;

/**
 * @author Terry Zhao <2358269014@qq.com>
 * @since 1.0
 */
class ProductController extends AppapiTokenController
{
    public $numPerPage = 5;
    
    /**
     * Get Lsit Api：得到Category 列表的api
     */
    public function actionList(){
        
        $page = Yii::$app->request->get('page');
        $third_product_code = Yii::$app->request->get('third_product_code');
        $third_refer_code = Yii::$app->request->get('third_refer_code');
        $spu = Yii::$app->request->get('spu');
        $whereArr = [];
        if ($third_product_code) {
            $whereArr[] = ['third_product_code' => $third_product_code ];
        }
        if ($third_refer_code) {
            $whereArr[] = ['third_refer_code' => $third_refer_code ];
        }
        if ($spu) {
            $whereArr[] = ['spu' => $spu ];
        }
        $page = $page ? $page : 1;
        $filter = [
            'numPerPage'    => $this->numPerPage,
            'pageNum'       => $page,
            'asArray'       => true,
        ];
        if (!empty($whereArr)) {
            $filter['where'] = $whereArr;
        }
        //var_dump($filter);exit;
        $data  = Yii::$service->product->coll($filter);
        $coll  = $data['coll'];
        foreach ($coll as $k => $one) {
            // 处理mongodb类型
            if (isset($one['_id'])) {
                $coll[$k]['id'] = (string)$one['_id'];
                unset($coll[$k]['_id']);
            }
        }
        $count = $data['count'];
        $pageCount = ceil($count / $this->numPerPage);
        $serializer = new \yii\rest\Serializer();
        Yii::$app->response->getHeaders()
            ->set($serializer->totalCountHeader, $count)
            ->set($serializer->pageCountHeader, $pageCount)
            ->set($serializer->currentPageHeader, $page)
            ->set($serializer->perPageHeader, $this->numPerPage);
        if ($page <= $pageCount ) {
            return [
                'code'    => 200,
                'message' => 'fetch product success',
                'data'    => $coll,
            ];
        } else if ($pageCount == 0){
            return [
                'code'    => 200,
                'message' => 'no product',
                'data'    => $coll,
            ];
        } else {
            return [
                'code'    => 400,
                'message' => 'fetch product fail , exceeded the maximum number of pages',
                'data'    => [],
            ];
        }
    }
    /**
     * Get One Api：根据url_key 和 id 得到Category 列表的api
     */
    public function actionFetchone(){
        $primaryKeyVal = Yii::$app->request->get('id');
        $data          = [];
        if (!$primaryKeyVal ) {
            return [
                'code'    => 400,
                'message' => 'request param [url_key,id] can not all empty',
                'data'    => [],
            ];
        } else if ($primaryKeyVal) {
            $product = Yii::$service->product->apiGetByPrimaryKey($primaryKeyVal);
            if(!empty($product)){
                $data = $product;
            }
        }
        if (empty($data)) {
            return [
                'code'    => 400,
                'message' => 'can not find product by id ',
                'data'    => [],
            ];
        } else {
            // 处理mongodb类型
            if (isset($data['_id'])) {
                $data['id'] = (string)$data['_id'];
                unset($data['_id']);
            }
            return [
                'code'    => 200,
                'message' => 'fetch product success',
                'data'    => $data,
            ];
        } 
    }
    /**
     * Add One Api：新增一条记录的api
     */
    public function actionAddone()
    {
        //var_dump(Yii::$app->request->post());exit;
        $data = Yii::$service->product->productapi->insertByPost();
        
        return $data;
    }
    /**
     * Upsert One Api：新增一条记录的api
     */
    public function actionUpsertone()
    {
        //var_dump(Yii::$app->request->post());exit;
        $productOne = Yii::$app->request->post('product');
        $spu = $productOne['spu'];
        Yii::$service->product->addGroupAttrs($productOne['attr_group']);
        $originUrlKey   = 'catalog/product/index';
        // 如果已经存在产品，则不更新库存
        $productM = Yii::$service->product->getByPrimaryKey($productOne['id']);
        if (isset($productM['sku']) && $productM['sku']) {
            unset($productOne['qty']);
        }
        $saveData       = Yii::$service->product->upsert($productOne, $originUrlKey);
        $errors         = Yii::$service->helper->errors->get();
        if (!$errors) {
            $saveData = $saveData->attributes;
            if (isset($saveData['_id'])) {
                $saveData['id'] = (string)$saveData['_id'];
                unset($saveData['_id']);
            }
            
            return [
                'code'    => 200,
                'message' => 'add product success',
                'data'    => [
                    'addData' => $saveData,
                ]
            ];
        } else {
            
            return [
                'code'    => 400,
                'message' => 'save product fail',
                'data'    => [
                    'error' => $errors,
                ],
            ];
        }
        
    }
    /**
     * Update One Api：更新一条记录的api
     */
    public function actionUpdateone(){
        //var_dump(Yii::$app->request->post());exit;
        // 必填
        $id                 = Yii::$app->request->post('id');
        $name               = Yii::$app->request->post('name');
        $weight             = Yii::$app->request->post('weight');
        $status             = Yii::$app->request->post('status');
        $qty                = Yii::$app->request->post('qty');
        $is_in_stock        = Yii::$app->request->post('is_in_stock');
        $category           = Yii::$app->request->post('category');
        $price              = Yii::$app->request->post('price');
        $special_price      = Yii::$app->request->post('special_price');
        $special_from       = Yii::$app->request->post('special_from');
        $special_to         = Yii::$app->request->post('special_to');
        $cost_price         = Yii::$app->request->post('cost_price');
        $tier_price         = Yii::$app->request->post('tier_price');
        $new_product_from   = Yii::$app->request->post('new_product_from');
        $new_product_to     = Yii::$app->request->post('new_product_to');
        $short_description  = Yii::$app->request->post('short_description');
        $remark             = Yii::$app->request->post('remark');
        $relation_sku       = Yii::$app->request->post('relation_sku');
        $buy_also_buy_sku   = Yii::$app->request->post('buy_also_buy_sku');
        $see_also_see_sku   = Yii::$app->request->post('see_also_see_sku');
        $title              = Yii::$app->request->post('title');
        $meta_keywords      = Yii::$app->request->post('meta_keywords');
        $meta_description   = Yii::$app->request->post('meta_description');
        $description        = Yii::$app->request->post('description');
        if (!$id) {
            $error[] = '[id] can not empty';
        }
        if ($name && !Yii::$service->fecshoplang->getDefaultLangAttrVal($name, 'name')) {
            $defaultLangAttrName = Yii::$service->fecshoplang->getDefaultLangAttrName('name');
            $error[] = '[name.'.$defaultLangAttrName.'] can not empty';
        }
        if ($meta_keywords && !is_array($meta_keywords)) {
            $error[] = '[meta_keywords] must be array';
        }
        if ($meta_description && !is_array($meta_description)) {
            $error[] = '[meta_description] must be array';
        }
        if ($description && !is_array($description)) {
            $error[] = '[description] must be array';
        }
        if ($short_description && !is_array($short_description)) {
            $error[] = '[short_description] must be array';
        }
        if ($title && !is_array($title)) {
            $error[] = '[title] must be array';
        }
        if (!empty($error)) {
            return [
                'code'    => 400,
                'message' => 'data param format error',
                'data'    => [
                    'error' => $error,
                ],
            ];
        }
        $param = Yii::$app->request->post();
        
        $primaryKey         = Yii::$service->product->getPrimaryKey();
        $param[$primaryKey] = $id;
        
        $saveData = Yii::$service->product->save($param);
        if (!$saveData) {
            $errors = Yii::$service->helper->errors->get();
            return [
                'code'    => 400,
                'message' => 'update product fail',
                'data'    => [
                    'error' => $errors,
                ],
            ];
        } else {
            return [
                'code'    => 200,
                'message' => 'update product success',
                'data'    => [
                    'updateData' => $saveData,
                ]
            ];
        }
    }
    /**
     * Delete One Api：删除一条记录的api
     */
    public function actionDeleteone(){
        $ids = Yii::$app->request->post('ids');
        Yii::$service->product->remove($ids);
        $errors = Yii::$service->helper->errors->get();
        if (!empty($errors)) {
            return [
                'code'    => 400,
                'message' => 'remove product by ids fail',
                'data'    => [
                    'error' => $errors,
                ],
            ];
        } else {
            return [
                'code'    => 200,
                'message' => 'remove product by ids success',
                'data'    => []
            ];
        }
    }
    
   public $stockTypeReplace = 'replace';
   public $stockTypeUpdateCounter = 'updateCounter';
   
    
    public function actionUpdateqty(){
        $items = Yii::$app->request->post('items');
        if (!is_array($items) || empty($items)) {
            return [
                'code'    => 400,
                'message' => 'items format error',
                'data'    => [
                    'error' => 'items format error',
                ],
            ];
        }
        // 检查数据
        $errors = '';
        $arr = [];
        foreach ($items as $item) {
            $product_id = isset($item['product_id']) ? $item['product_id'] : '';
            $sku = isset($item['sku']) ? $item['sku'] : '';
            $qty = isset($item['qty']) ? $item['qty'] : '';
            $type = isset($item['type']) ? $item['type'] : '';
            if (!$product_id && !$sku) {
                $errors = 'product_id and sku, can not all empty';
                break;
            }
            if (!$type || ($type != $this->stockTypeReplace && $type != $this->stockTypeUpdateCounter)) {
                $errors = 'type ['.$type .']  is error, It can only choose one of these values["'.$this->stockTypeReplace.'", "'.$this->stockTypeUpdateCounter.'"]';
                break;
            }
            if (!$qty) {
                $errors = 'qty can not empty';
                break;
            }
            if (!$product_id) {
                $productModel = Yii::$service->product->getBySku($sku);
                $productPrimaryKey = Yii::$service->product->getPrimaryKey();
                $product_id= isset($productModel[$productPrimaryKey]) ? $productModel[$productPrimaryKey] : '';
            } else {
                $productModel = Yii::$service->product->getByPrimaryKey($product_id);
                if (!$productModel['sku']) {
                    $errors = 'product product_id['.$product_id.'] is not exist';
                    break;
                }
                $sku = $productModel['sku'];
            }
            if (!$product_id) {
                $errors = 'product sku['.$sku.'] is not exist';
                break;
            }
            
            $arr[] = [
                'product_id' => $product_id,
                'sku' => $sku,
                'type' => $type,
                'qty' => $qty,
            ];
        }
        if ($errors) {
            return [
                'code'    => 400,
                'message' => 'items data error',
                'data'    => [
                    'error' => $errors,
                ],
            ];
        }
        $errorInfos = [];
        foreach ($arr as $one) {
            $product_id = $one['product_id'];
            $sku = $one['sku'];
            $type = $one['type'];
            $qty = $one['qty'];
            if ($type == $this->stockTypeReplace) {
                if (!Yii::$service->product->stock->saveProductStock($product_id, ['qty' => $qty])) {
                    $errors = Yii::$service->helper->errors->get(',');
                    if ($errors) {
                        $errorInfos[] = "Product Update Stock Errors:".$errors;
                    } else {
                        $errorInfos[] = "Product Update Stock Errors: product stock saveProductStock error";
                    } 
                }
            } else if ($type == $this->stockTypeUpdateCounter) {
                $upitems = [
                    [ 'sku' => $sku, 'qty' => $qty,]
                ];
                //var_dump($upitems);exit;
                if (!Yii::$service->product->stock->Updatebybase($upitems)) {
                    $errors = Yii::$service->helper->errors->get(',');
                    if ($errors) {
                        $errorInfos[] = "Product Update Stock Errors:".$errors;
                    } else {
                        $errorInfos[] = "Product Update Stock Errors: product stock Updatebybase error";
                    } 
                    
                }
            }
        }
        
        if (!empty($errorInfos)) {
            return [
                'code'    => 400,
                'message' => 'items data error',
                'data'    => [
                    'error' => implode(',', $errorInfos),
                ],
            ];
        }
        
        return [
            'code'    => 200,
            'message' => 'update stock success',
            'data'    => []
        ];
        
    }
    
    
    /**
     * Shopfw Add One Api：采集工具Shopfw新增产品数据的api
     */
    public function actionShopfwaddone(){
        //var_dump(Yii::$app->request->post());exit;
        
        $products = Yii::$app->request->post('products');
        
        $data = Yii::$service->product->productapi->insertByShopfwPost($products);
        
        if ($data) {
            return [
                'code'    => 200,
                'message' => 'add product success',
                'data'    => [
                    'addData' => $data,
                ]
            ];
        }
        
        return [
            'code'    => 400,
            'message' => 'add product fail',
            'data'    => [
                'error' => Yii::$service->helper->errors->get(', '),
            ]
        ];
    }
    
    
    public function actionUpdatestockandqty()
    {
        $items = Yii::$app->request->post('items');
        if (!is_array($items) || empty($items)) {
            return [
                'code'    => 400,
                'message' => 'items is not array ',
                'data'    => [
                    'error' => 'items is not array ',
                ]
            ];
        }
        
        foreach ($items as $item) {
            Yii::$service->product->updateStockAndPrice($item);
        }
        $errors = Yii::$service->helper->errors->get(', ');
        if ($errors) {
            
            return [
                'code'    => 400,
                'message' => 'updateStockAndQty fail',
                'data'    => [
                    'error' => $errors,
                ]
            ];
        }
        
        return [
            'code'    => 200,
            'message' => 'add product success',
            'data'    => [ ]
        ];
        
    }
    
}
