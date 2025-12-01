<?php

namespace App\Constant;

enum Route: string
{
    // Simple routes
    case HOME = 'home';
    case LOGIN = 'login';
    case LOGOUT = 'logout';
    case REGISTER = 'register';
    case ADMIN = 'admin';

    // Shop routes
    case SHOP = 'shop';
    case SHOP_CATEGORY = 'shop_category';
    case SHOP_PRODUCT = 'shop_product';

    // Product routes
    case PRODUCT = 'product';
    case PRODUCT_SHOW = 'product_show';
    case PRODUCT_EDIT = 'product_edit';
    case PRODUCT_NEW = 'product_new';
    case PRODUCT_DELETE = 'product_delete';

    // Category routes
    case CATEGORY = 'category';
    case CATEGORY_SHOW = 'category_show';
    case CATEGORY_EDIT = 'category_edit';
    case CATEGORY_NEW = 'category_new';
    case CATEGORY_DELETE = 'category_delete';

    // Cart routes
    case CART = 'cart';
    case CART_ADD = 'cart_add';
    case CART_UPDATE = 'cart_update';
    case CART_REMOVE = 'cart_remove';
    case CART_CLEAR = 'cart_clear';

    /**
     * Generate a child route name
     *
     * @param string $action The action suffix (show, edit, new, delete, add, etc.)
     * @return string The full route name
     */
    public function child(string $action): string
    {
        return $this->value . '_' . $action;
    }

    /**
     * Common CRUD actions
     */
    public function index(): string
    {
        return $this->value;
    }

    public function show(): string
    {
        return $this->child('show');
    }

    public function new(): string
    {
        return $this->child('new');
    }

    public function edit(): string
    {
        return $this->child('edit');
    }

    public function delete(): string
    {
        return $this->child('delete');
    }

    /**
     * Cart-specific actions
     */
    public function add(): string
    {
        return $this->child('add');
    }

    public function update(): string
    {
        return $this->child('update');
    }

    public function remove(): string
    {
        return $this->child('remove');
    }

    public function clear(): string
    {
        return $this->child('clear');
    }

    /**
     * Shop-specific routes
     */
    public function category(): string
    {
        return $this->child('category');
    }

    public function product(): string
    {
        return $this->child('product');
    }
}
