# solidtime: importação da agenda do Outlook

Fork do [solidtime](https://github.com/solidtime-io/solidtime) **v0.21.0** com a **importação da agenda do Outlook**:
cada pessoa conecta a própria agenda do Microsoft 365 e transforma as reuniões que já aconteceram em apontamentos.
O resto é o solidtime original. English: [README.md](README.md).

## Por que

Reunião é boa parte da semana de muita equipe, e redigitar tudo à mão é o que faz as pessoas desistirem de apontar
horas. A agenda já tem o assunto, o início e o fim. Este fork lê a agenda e transforma cada reunião em apontamento,
e assim uma semana de reuniões se registra em um minuto.

Ele atende, para quem usa Microsoft 365, o pedido da
[discussão #358](https://github.com/solidtime-io/solidtime/discussions/358).

## O que faz

- **Conectar Outlook:** pela tela de Apontamentos, cada pessoa conecta a própria agenda (Microsoft Entra ID, delegado,
  só leitura, authorization code com PKCE).
- **Importar da agenda:** lista as reuniões desde a última importação, sem canceladas, dia inteiro, recusadas ou
  "livre". Cada uma vem com projeto, tarefa e tags sugeridos, aprendidos dos apontamentos da própria pessoa.
- **Importar ou ignorar:** a pessoa escolhe o que fica, e cada reunião vira um apontamento normal, não faturável.
  - Nenhuma reunião entra duas vezes.
  - A regra de "impedir apontamentos sobrepostos" da organização é respeitada.
- **Desligado por padrão:** sem `ENTRA_CALENDAR_ENABLED=true` e os dados do app no Entra, nada aparece e as rotas
  respondem 404.

Configuração, segurança e API: **[OUTLOOK-CALENDAR.md](OUTLOOK-CALENDAR.md)** (em inglês).

## Base

Fica sobre o [solidtime-pt-br](https://github.com/rtsantanna/solidtime-pt-br), porque os textos da tela usam as funções
de tradução que vêm de lá. O recurso funciona em qualquer idioma, e os textos dele em português estão incluídos.

## Verificado

Construído e testado sobre o solidtime v0.21.0 com PHP 8.3 (outubro de 2026): suíte PHP 1.156/1.156 (24 testes deste
recurso, com o Microsoft Graph e o Entra ID simulados), PHPStan sem erros, Pint, Vitest 204/204, vue-tsc, ESLint sem
erros e build de produção.

## Licença e créditos

GNU AGPL-3.0, a mesma do solidtime ([LICENSE.md](LICENSE.md)). O crédito do aplicativo é todo da
[equipe do solidtime](https://github.com/solidtime-io/solidtime).
