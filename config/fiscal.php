<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Fiscal Driver
    |--------------------------------------------------------------------------
    |
    | Driver para emissão de documentos fiscais eletrônicos.
    | Opções: 'fake' (testes), 'focus_nfe' (Focus NFe)
    |
    */
    'driver' => env('FISCAL_DRIVER', 'fake'),

    /*
    |--------------------------------------------------------------------------
    | Auto Emit on Payment
    |--------------------------------------------------------------------------
    |
    | Se true, emite NFSe automaticamente quando uma invoice é paga.
    |
    */
    'auto_emit_on_payment' => env('FISCAL_AUTO_EMIT', true),

    /*
    |--------------------------------------------------------------------------
    | Emitter (Dados da Empresa Emissora — Plataforma SaaS)
    |--------------------------------------------------------------------------
    */
    'emitter' => [
        'cnpj' => env('FISCAL_EMITTER_CNPJ'),
        'razao_social' => env('FISCAL_EMITTER_RAZAO_SOCIAL'),
        'inscricao_municipal' => env('FISCAL_EMITTER_IM'),
        'codigo_municipio' => env('FISCAL_EMITTER_COD_MUNICIPIO'),
        'uf' => env('FISCAL_EMITTER_UF'),
        'cnae' => env('FISCAL_EMITTER_CNAE'),
        'codigo_servico' => env('FISCAL_EMITTER_COD_SERVICO'),
        'iss_rate' => (float) env('FISCAL_EMITTER_ISS_RATE', 5.00),
        'regime_tributario' => env('FISCAL_EMITTER_REGIME', 'simples_nacional'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Focus NFe
    |--------------------------------------------------------------------------
    */
    'focus_nfe' => [
        'token' => env('FOCUS_NFE_TOKEN'),
        'environment' => env('FOCUS_NFE_ENV', 'homologation'),
        'webhook_secret' => env('FOCUS_NFE_WEBHOOK_SECRET'),
        'base_url' => env('FOCUS_NFE_BASE_URL', 'https://homologacao.focusnfe.com.br'),
    ],

];
