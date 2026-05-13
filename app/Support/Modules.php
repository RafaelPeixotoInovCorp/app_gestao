<?php

namespace App\Support;

class Modules
{
    public const ITEMS = [
        ['key' => 'clients', 'label' => 'Clientes', 'route' => 'modules.show', 'slug' => 'clients'],
        ['key' => 'suppliers', 'label' => 'Fornecedores', 'route' => 'modules.show', 'slug' => 'suppliers'],
        ['key' => 'contacts', 'label' => 'Contactos', 'route' => 'modules.show', 'slug' => 'contacts'],
        ['key' => 'proposals', 'label' => 'Propostas', 'route' => 'modules.show', 'slug' => 'proposals'],
        ['key' => 'calendar', 'label' => 'Calendário', 'route' => 'modules.show', 'slug' => 'calendar'],
        ['key' => 'customer-orders', 'label' => 'Encomendas - Clientes', 'route' => 'modules.show', 'slug' => 'customer-orders'],
        ['key' => 'supplier-orders', 'label' => 'Encomendas - Fornecedores', 'route' => 'modules.show', 'slug' => 'supplier-orders'],
        ['key' => 'work-orders', 'label' => 'Ordens de Trabalho', 'route' => 'modules.show', 'slug' => 'work-orders'],
        ['key' => 'financial-bank-accounts', 'label' => 'Financeiro - Contas Bancárias', 'route' => 'modules.show', 'slug' => 'financial-bank-accounts'],
        ['key' => 'financial-customer-ledger', 'label' => 'Financeiro - Conta Corrente Clientes', 'route' => 'modules.show', 'slug' => 'financial-customer-ledger'],
        ['key' => 'financial-supplier-invoices', 'label' => 'Financeiro - Faturas Fornecedores', 'route' => 'modules.show', 'slug' => 'financial-supplier-invoices'],
        ['key' => 'digital-archive', 'label' => 'Arquivo Digital', 'route' => 'modules.show', 'slug' => 'digital-archive'],
        ['key' => 'users', 'label' => 'Gestão de acessos - Utilizadores', 'route' => 'modules.show', 'slug' => 'users'],
        ['key' => 'permissions', 'label' => 'Gestão de acessos - Permissões', 'route' => 'modules.show', 'slug' => 'permissions'],
        ['key' => 'settings-countries', 'label' => 'Configurações - Entidades - Países', 'route' => 'modules.show', 'slug' => 'settings-countries'],
        ['key' => 'settings-contact-roles', 'label' => 'Configurações - Contactos - Funções', 'route' => 'modules.show', 'slug' => 'settings-contact-roles'],
        ['key' => 'settings-calendar-types', 'label' => 'Configurações - Calendário - Tipos', 'route' => 'modules.show', 'slug' => 'settings-calendar-types'],
        ['key' => 'settings-calendar-actions', 'label' => 'Configurações - Calendário - Ações', 'route' => 'modules.show', 'slug' => 'settings-calendar-actions'],
        ['key' => 'settings-items', 'label' => 'Configurações - Artigos', 'route' => 'modules.show', 'slug' => 'settings-items'],
        ['key' => 'settings-vat', 'label' => 'Configurações - Financeiro - IVA', 'route' => 'modules.show', 'slug' => 'settings-vat'],
        ['key' => 'settings-logs', 'label' => 'Configurações - Logs', 'route' => 'modules.show', 'slug' => 'settings-logs'],
        ['key' => 'settings-company', 'label' => 'Configurações - Empresa', 'route' => 'modules.show', 'slug' => 'settings-company'],
    ];
}
