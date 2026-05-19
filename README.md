# DigitalSac Contratos (WHMCS Addon)

Addon open source para gestão de contratos digitais no WHMCS, com geração, envio, assinatura eletrônica e trilha de auditoria.

## Destaques

- Geração de contrato por template HTML com variáveis dinâmicas.
- Assinatura eletrônica por `canvas` e/ou aceite por checkbox.
- Link público de assinatura com token e prazo de validade.
- Envio por e-mail e integração com WhatsApp (drivers configuráveis).
- Geração automática via hook de fatura (`InvoiceCreated`).
- Suporte a múltiplas contratadas.
- Auditoria completa de eventos e assinaturas.

## Requisitos

- WHMCS `9.0+`
- PHP `8.2+`

## Instalação

1. Copie a pasta `mpcontratos/` para `modules/addons/` do WHMCS.
2. No WHMCS, acesse `Setup > Addon Modules`.
3. Ative **DigitalSac Contratos** e clique em **Configure**.
4. Defina permissões (Admin Role) e salve.
5. Acesse `Addons > DigitalSac Contratos`.

## Configurações principais

No `Setup > Addon Modules > DigitalSac Contratos > Configure`:

- `cpf_cnpj_field_id`
- `company_name_source`
- `company_name_field_id`
- `contract_prefix`
- `public_link_ttl`
- `auto_generate_on_invoice`
- `whatsapp_driver`
- `whatsapp_endpoint`
- `whatsapp_token`
- `whatsapp_session`
- `signature_methods`

## Fluxo recomendado

1. Cadastre a(s) contratada(s).
2. Crie os templates de contrato.
3. Faça mapeamento de Produtos × Templates.
4. (Opcional) Configure mapeamento de custom fields.
5. Gere contrato manualmente ou automaticamente pela fatura.
6. Envie ao cliente (e-mail/WhatsApp/link público).
7. Acompanhe assinatura e auditoria no painel.

## Variáveis de template (exemplos)

```txt
{{cliente.nome}}
{{cliente.empresa}}
{{cliente.cpf_cnpj}}
{{servico.nome}}
{{servico.valor_formatado}}
{{contrato.numero}}
{{contrato.data_geracao_extenso}}
{{contratada.razao_social}}
{{contratada.assinatura_data_uri}}
{{custom.seu_campo}}
```

## Segurança e auditoria

- Proteção CSRF nos formulários.
- Hash de integridade SHA-256 na assinatura.
- Registro de IP, user-agent, data/hora e método de assinatura.
- Token de link público com expiração.

## Estrutura resumida

```txt
mpcontratos/
├── mpcontratos.php
├── hooks.php
├── lib/
├── templates/
├── public/
├── languages/
└── storage/
```

## Licença e atribuição (IMPORTANTE)

Este projeto é aberto para uso, estudo, modificação e distribuição, desde que:

1. Seja mantido o crédito ao desenvolvedor original:
   **DigitalSac Software Engineering**.
2. Alterações e forks informem claramente quem desenvolveu o projeto original.
3. Quem modificar o código identifique que fez alterações (ex.: `Modified by ...`).

### Licença recomendada para este termo

Para atender esse modelo de uso aberto com preservação de atribuição, a recomendação é:

- **Apache License 2.0**, mantendo:
  - arquivo `LICENSE`;
  - créditos no `README`;
  - avisos de autoria nos arquivos relevantes;
  - indicação de alterações quando houver modificações.

> Observação: o identificador técnico do módulo (`mpcontratos`) foi mantido por compatibilidade com o WHMCS.

## Créditos

- **Desenvolvido por:** DigitalSac Software Engineering
- **Projeto:** DigitalSac Contratos (WHMCS Addon)
