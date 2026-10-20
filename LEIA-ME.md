# solidtime em português do Brasil

Fork do [solidtime](https://github.com/solidtime-io/solidtime) **v0.21.0** com a interface internacionalizada e a
**tradução completa para o português do Brasil**. O resto é o solidtime original. English: [README.md](README.md).

## Por que

A interface do solidtime só existe em inglês: quase todo o texto está fixo nos templates Vue e em algumas classes PHP,
então não basta colocar um arquivo de idioma. Este fork passa esses textos pelas funções de tradução padrão do
Laravel e do Vue e traz um catálogo pt-BR revisado. Assim, telas, e-mails, relatórios e PDFs saem em português.
O inglês continua sendo o padrão, e chave sem tradução cai no inglês.

## O que muda

- **Front:** [laravel-vue-i18n](https://github.com/xiCO2k/laravel-vue-i18n) (a única dependência nova) lê
  `lang/<código>.json`, cujas chaves são o texto original em inglês.
  - Cerca de 830 textos fixos passam por `$t()` / `trans()` / `$tChoice()`.
  - O idioma vem do `<html lang>`, e os nomes de mês e dia da semana do dayjs acompanham.
- **Back:** variáveis `APP_LOCALE` e `APP_FALLBACK_LOCALE`. Passam por `__()`:
  - papéis;
  - erros do importador;
  - PDFs e planilhas de relatório;
  - tela de consentimento OAuth;
  - algumas mensagens de validação e de controller.
- **pt-BR:** `lang/pt_BR.json` (1.165 chaves) e `lang/pt_BR/*.php`. Os textos do framework vêm do
  [laravel-lang](https://github.com/Laravel-Lang/lang); os do projeto foram traduzidos e revisados à mão.
- **Sem migração de banco.** Sem `APP_LOCALE`, nada muda.

## Como usar

Construa e publique como o solidtime original (`docker/prod`) e defina:

```env
APP_LOCALE=pt_BR
APP_FALLBACK_LOCALE=en
```

O idioma vale para a instalação inteira, não por usuário.

## Outro idioma

1. Copie `lang/pt_BR.json` para `lang/<código>.json` e traduza os valores, mantendo as chaves (o texto em inglês).
2. Crie `lang/<código>/*.php`: os arquivos do framework vêm do laravel-lang; os do projeto são `enum.php`,
   `enums.php`, `exceptions.php` e `importer.php`.
3. Defina `APP_LOCALE=<código>`.

## Verificado

Construído e testado sobre o solidtime v0.21.0 com PHP 8.3, a versão que o original exige (outubro de 2026): suíte PHP
1.132/1.132, PHPStan sem erros, Pint, Vitest 204/204, vue-tsc, ESLint sem erros e build de produção.

## Relacionados

- [solidtime-oidc](https://github.com/rtsantanna/solidtime-oidc): login único OIDC genérico para o solidtime. Os dois
  forks são pequenos, rebaseáveis e se combinam sem conflito na v0.21.0.

## Licença e créditos

GNU AGPL-3.0, a mesma do solidtime ([LICENSE.md](LICENSE.md)). O crédito do aplicativo é todo da
[equipe do solidtime](https://github.com/solidtime-io/solidtime). Os textos do framework vêm do
[laravel-lang](https://github.com/Laravel-Lang/lang) (MIT).
