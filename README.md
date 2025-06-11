# Plugin AccountStatus
> Plugin de gerenciamento de selos de status de usuários na plataforma Mapas Culturais

## Efeitos do uso do plugin

- O plugin adiciona automaticamente selos aos agentes individuais da plataforma, classificando usuários como **Inativos** ou com **Cadastro Atualizado**, com base no último acesso ao sistema e na data da última atualização de cadastro. Isso auxilia os administradores na identificação rápida do nível de atividade e atualização dos perfis de usuários.

## Requisitos Mínimos
- Mapas Culturais v7.6.0^

## Funcionamento

### Selo de **Usuário Inativo**
- Após **1 ano sem acesso** à plataforma, o agente individual vinculado ao usuário recebe o selo de **Usuário Inativo**.
- O sistema executa **uma rotina diária** para identificar esses casos e aplicar o selo automaticamente.
  
### Selo de **Cadastro Atualizado**
- O selo é concedido se o usuário atualizou **todos os campos obrigatórios** colocados na configuração nos últimos 12 meses.
- Um sistema de verificação diária garante que o selo permaneça apenas para perfis atualizados.
- Quando a validade se aproxima do fim (com 30 dias de antecedência), um **aviso do sistema** é emitido alertando sobre a iminente expiração do selo.
- Caso a atualização não ocorra dentro do prazo, o selo é **removido automaticamente**.

## Configuração básica

### Para ativar o plugin no ambiente de desenvolvimento ou produção

- No arquivo `docker/common/config.d/plugins.php`, adicione `'AccountStatus'`:

```php
<?php

return [
    'plugins' => [
        'MultipleLocalAuth' => [ 'namespace' => 'MultipleLocalAuth' ],
        'SamplePlugin' => ['namespace' => 'SamplePlugin'],
        "AccountStatus",
    ]
];
```

### Configurações adicionais

- O plugin permite a personalização dos parâmetros de verificação por meio da chave `config`, conforme exemplo abaixo:

```php
    <?php

    return [
        'plugins' => [
            "AccountStatus" => [
                "namespace" => "AccountStatus",
                "config" => [
                    'inactive_seal_id' => env('USR_STATUS_INACTIVE_SEAL_ID', 5),
                    'inactive_period' => env('USR_STATUS_INACTIVE_PERIOD', '-1 year'),
                    'updated_seal_id' => env('USR_STATUS_UPDATED_SEAL_ID', 4),
                    'update_expiration_period' => env('USR_STATUS_LAST_UPDATE', '+1 year'),
                    'update_fields' => [
                        'name',
                        'shortDescription',
                    ],
                ]
            ]
        ]
    ];
```

- **Descrição dos parâmetros:**
  - `inactive_seal_id`: ID do selo usado para identificar usuários inativos.
  - `inactive_period`: Período de inatividade para considerar o usuário como inativo (ex: `-1 year`).
  - `updated_seal_id`: ID do selo usado para marcar o cadastro como atualizado.
  - `update_expiration_period`: Tempo de validade para considerar um cadastro como atualizado (ex: `+1 year`).
  - `update_fields`: Campos obrigatórios que devem ter sido atualizados dentro do período para garantir o selo.

- **IMPORTANTE:** Os valores podem ser definidos diretamente ou usando variáveis de ambiente via `env()`.

## Notificações

- O sistema envia **e-mail automático** aos usuários quando a validade do selo de "Cadastro Atualizado" está prestes a expirar.
- Os administradores podem visualizar os selos diretamente no painel dos agentes.

## Observações

- Os selos são exibidos na interface pública e administrativa da plataforma, reforçando a transparência quanto à atualização e atividade dos perfis.
- A verificação dos status ocorre automaticamente uma vez por dia, sem necessidade de intervenção manual.

