<div align="center">

# DigitalSac Contratos

### Addon WHMCS para gestão de contratos digitais com assinatura eletrônica

[![License: Apache 2.0](https://img.shields.io/badge/License-Apache_2.0-blue.svg?style=for-the-badge&logo=apache)](https://www.apache.org/licenses/LICENSE-2.0)
[![Open Source](https://img.shields.io/badge/Open%20Source-%E2%9C%94-success?style=for-the-badge)](#licença-e-atribuição)
[![Made by DigitalSac](https://img.shields.io/badge/Made%20by-DigitalSac%20Software%20Engineering-ff4081?style=for-the-badge)](#créditos)

[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=flat-square&logo=php&logoColor=white)](https://www.php.net/)
[![WHMCS](https://img.shields.io/badge/WHMCS-9.0%2B-2962ff?style=flat-square)](https://www.whmcs.com/)
[![Status](https://img.shields.io/badge/status-stable-brightgreen?style=flat-square)](#)
[![PRs Welcome](https://img.shields.io/badge/PRs-welcome-orange?style=flat-square)](#contribuindo)

</div>

---

> **Licenciado sob a [Apache License 2.0](LICENSE).**
> Você pode usar, modificar e redistribuir, **mantendo o crédito ao desenvolvedor original** e **indicando quaisquer alterações** que fizer.
> Veja [Licença e atribuição](#licença-e-atribuição) para os detalhes.

---

## Sumário

- [Destaques](#destaques)
- [Requisitos](#requisitos)
- [Instalação](#instalação)
- [Configurações principais](#configurações-principais)
- [Fluxo recomendado](#fluxo-recomendado)
- [Variáveis de template](#variáveis-de-template)
- [Segurança e auditoria](#segurança-e-auditoria)
- [Estrutura do projeto](#estrutura-do-projeto)
- [Licença e atribuição](#licença-e-atribuição)
- [Contribuindo](#contribuindo)
- [Créditos](#créditos)

## Destaques

- Geração de contratos a partir de templates HTML com variáveis dinâmicas.
- Assinatura eletrônica por `canvas` (manuscrita) e/ou aceite por `checkbox`.
- Link público de assinatura com token e prazo de validade.
- Envio por e-mail e integração com WhatsApp (drivers configuráveis).
- Geração automática via hook de fatura (`InvoiceCreated`).
- Suporte a **múltiplas contratadas** (multi-empresa emissora).
- Suporte opcional a **certificado digital A1** (ICP-Brasil) na PDF da contratada.
- Auditoria completa de eventos e assinaturas (IP, UA, hash SHA-256).

## Requisitos

| Item   | Versão  |
| ------ | ------- |
| WHMCS  | `9.0+`  |
| PHP    | `8.2+`  |
| Banco  | MySQL/MariaDB (via WHMCS) |

## Instalação

1. Copie a pasta `mpcontratos/` para `modules/addons/` do seu WHMCS.
2. No WHMCS, acesse `Setup > Addon Modules`.
3. Ative **DigitalSac Contratos** e clique em **Configure**.
4. Defina permissões em **Access Control** e salve.
5. Acesse `Addons > DigitalSac Contratos` para começar.

> O identificador técnico do módulo permanece `mpcontratos` por compatibilidade com o WHMCS. A marca exibida é **DigitalSac Contratos**.

## Configurações principais

Em `Setup > Addon Modules > DigitalSac Contratos > Configure`:

| Chave | Descrição |
| --- | --- |
| `cpf_cnpj_field_id` | Custom field do WHMCS com o CPF/CNPJ |
| `company_name_source` | `native` ou `custom_field` |
| `company_name_field_id` | ID do custom field, se aplicável |
| `contract_prefix` | Prefixo do número do contrato |
| `public_link_ttl` | Validade do link público (horas) |
| `auto_generate_on_invoice` | Gerar contrato ao criar fatura |
| `whatsapp_driver` | `disabled`, `digitalsac`, `zuckzapgo`, `generic` |
| `whatsapp_endpoint` | URL base do driver |
| `whatsapp_token` | Token / Bearer |
| `whatsapp_session` | Sessão / Instance ID |
| `signature_methods` | Métodos de assinatura (`canvas,checkbox`) |

## Fluxo recomendado

1. Cadastre a(s) **Contratada(s)** (empresa emissora).
2. Crie os **Templates** de contrato em HTML.
3. Configure **Produtos × Templates × Contratadas**.
4. (Opcional) Mapeie **Custom Fields** para variáveis (`{{custom.<slug>}}`).
5. Gere contratos manualmente ou automaticamente via fatura.
6. Envie ao cliente por e-mail, WhatsApp ou link público.
7. Acompanhe assinatura, auditoria e PDF no painel.

## Variáveis de template

```txt
{{cliente.nome}}
{{cliente.empresa}}
{{cliente.cpf_cnpj}}
{{cliente.endereco_completo}}
{{cliente.email}}
{{cliente.telefone}}

{{servico.nome}}
{{servico.valor_formatado}}
{{servico.valor_extenso}}
{{servico.ciclo}}
{{servico.dominio}}

{{contrato.numero}}
{{contrato.validade}}
{{contrato.data_geracao_extenso}}

{{contratada.razao_social}}
{{contratada.cnpj}}
{{contratada.signatory_name}}
{{contratada.signatory_role}}
{{contratada.assinatura_data_uri}}

{{custom.seu_campo}}
```

Filtros disponíveis: `upper`, `lower`, `trim`, `escape`.

## Segurança e auditoria

- Proteção **CSRF** em todos os formulários.
- **Hash SHA-256** de integridade calculado sobre o conteúdo + assinatura.
- Registro de **IP, user-agent, data/hora** e método em cada assinatura.
- **Token único** com expiração para links públicos.
- Suporte opcional a **assinatura digital A1 (ICP-Brasil)** no PDF.

## Estrutura do projeto

```txt
mpcontratos/
├── mpcontratos.php       # Entry point e callbacks WHMCS
├── hooks.php             # InvoiceCreated, DailyCronJob, EmailPreSend, etc.
├── lib/                  # Código (PSR-4: DigitalSac\MpContratos)
│   ├── Admin/            # Admin controller + views
│   ├── Client/           # Client area controller
│   ├── Contract/         # ContractManager, PdfGenerator, SignatureService...
│   ├── Database/         # Migrator
│   ├── Delivery/         # EmailSender + WhatsApp drivers
│   └── Support/          # Bootstrap, Csrf, Logger, Formatter, Context
├── templates/            # Smarty + exemplos HTML de contrato
├── public/               # Endpoint público de assinatura tokenizado
├── languages/            # pt-BR / en
└── storage/              # PDFs, assinaturas e certificados (gitignore)
```

---

## Licença e atribuição

[![License: Apache 2.0](https://img.shields.io/badge/License-Apache_2.0-blue.svg?style=for-the-badge&logo=apache)](LICENSE)

Este projeto é distribuído sob a **[Apache License 2.0](LICENSE)**.

Você **pode**:

- Usar comercial e privadamente.
- Modificar, distribuir e sublicenciar.
- Criar forks e versões derivadas.

Você **deve**:

1. **Manter o crédito ao desenvolvedor original**:
   **DigitalSac Software Engineering**.
2. **Indicar claramente que houve modificações**, quando aplicável
   (ex.: `Modified by <Seu Nome / Sua Empresa> in <data>`), nos arquivos alterados ou no `NOTICE`/`README` da sua versão.
3. **Preservar o arquivo `LICENSE`** e o `NOTICE` em redistribuições.
4. **Não usar a marca "DigitalSac"** para sugerir endosso da sua versão modificada sem autorização.

> Resumindo: pode usar e alterar à vontade, mas não tire o crédito original e deixe claro o que você mudou.

## Contribuindo

Pull requests são bem-vindos. Para mudanças grandes, abra uma issue antes para discutirmos a proposta.

1. Faça um fork.
2. Crie sua branch: `git checkout -b feature/minha-feature`.
3. Commit: `git commit -m "feat: minha feature"`.
4. Push: `git push origin feature/minha-feature`.
5. Abra um Pull Request.

## Créditos

- **Desenvolvido por:** **DigitalSac Software Engineering**
- **Projeto:** DigitalSac Contratos (WHMCS Addon)
- **Licença:** Apache License 2.0

<div align="center">

Made with care by **DigitalSac Software Engineering** • Licensed under **Apache 2.0**

</div>
