<?php

declare(strict_types=1);

use App\Exceptions\Api\CanNotDeleteUserWhoIsOwnerOfOrganizationWithMultipleMembers;
use App\Exceptions\Api\CanNotRemoveOwnerFromOrganization;
use App\Exceptions\Api\ChangingRoleOfPlaceholderIsNotAllowed;
use App\Exceptions\Api\ChangingRoleToPlaceholderIsNotAllowed;
use App\Exceptions\Api\EntityStillInUseApiException;
use App\Exceptions\Api\FeatureIsNotAvailableInFreePlanApiException;
use App\Exceptions\Api\InactiveUserCanNotBeUsedApiException;
use App\Exceptions\Api\InvitationForTheEmailAlreadyExistsApiException;
use App\Exceptions\Api\OnlyOwnerCanChangeOwnership;
use App\Exceptions\Api\OnlyPlaceholdersCanBeMergedIntoAnotherMember;
use App\Exceptions\Api\OrganizationHasNoSubscriptionButMultipleMembersException;
use App\Exceptions\Api\OrganizationNeedsAtLeastOneOwner;
use App\Exceptions\Api\OverlappingTimeEntryApiException;
use App\Exceptions\Api\PdfRendererIsNotConfiguredException;
use App\Exceptions\Api\PersonalAccessClientIsNotConfiguredException;
use App\Exceptions\Api\ThisPlaceholderCanNotBeInvitedUseTheMergeToolInsteadException;
use App\Exceptions\Api\TimeEntryCanNotBeRestartedApiException;
use App\Exceptions\Api\TimeEntryStillRunningApiException;
use App\Exceptions\Api\UserIsAlreadyMemberOfOrganizationApiException;
use App\Exceptions\Api\UserIsAlreadyMemberOfProjectApiException;
use App\Exceptions\Api\UserNotPlaceholderApiException;
use App\Exceptions\Api\UserResendEmailVerificationNoPendingEmailApiException;
use App\Service\Export\ExportException;

return [
    'api' => [
        TimeEntryStillRunningApiException::KEY => 'O apontamento ainda está em andamento',
        UserNotPlaceholderApiException::KEY => 'O usuário informado não é um membro provisório',
        TimeEntryCanNotBeRestartedApiException::KEY => 'O apontamento já foi parado e não pode ser reiniciado',
        InactiveUserCanNotBeUsedApiException::KEY => 'Não é possível usar um usuário inativo',
        UserIsAlreadyMemberOfOrganizationApiException::KEY => 'O usuário já é membro da organização',
        UserIsAlreadyMemberOfProjectApiException::KEY => 'O usuário já é membro do projeto',
        EntityStillInUseApiException::KEY => 'Não é possível excluir este item (:modelToDelete) porque ele ainda está em uso em: :modelInUse.',
        CanNotRemoveOwnerFromOrganization::KEY => 'Não é possível remover o proprietário da organização',
        CanNotDeleteUserWhoIsOwnerOfOrganizationWithMultipleMembers::KEY => 'Não é possível excluir um usuário que é proprietário de uma organização com vários membros. Exclua a organização primeiro.',
        OnlyOwnerCanChangeOwnership::KEY => 'Somente o proprietário pode transferir a propriedade',
        OrganizationNeedsAtLeastOneOwner::KEY => 'A organização precisa ter pelo menos um proprietário',
        ChangingRoleToPlaceholderIsNotAllowed::KEY => 'Não é permitido alterar o papel para membro provisório',
        ExportException::KEY => 'A exportação falhou. Tente novamente mais tarde ou entre em contato com o suporte',
        OrganizationHasNoSubscriptionButMultipleMembersException::KEY => 'A organização não tem assinatura, mas tem vários membros',
        PdfRendererIsNotConfiguredException::KEY => 'O gerador de PDF não está configurado',
        FeatureIsNotAvailableInFreePlanApiException::KEY => 'Este recurso não está disponível no plano gratuito',
        PersonalAccessClientIsNotConfiguredException::KEY => 'O cliente de acesso pessoal não está configurado',
        ChangingRoleOfPlaceholderIsNotAllowed::KEY => 'Não é permitido alterar o papel de um membro provisório',
        OnlyPlaceholdersCanBeMergedIntoAnotherMember::KEY => 'Somente membros provisórios podem ser mesclados em outro membro',
        ThisPlaceholderCanNotBeInvitedUseTheMergeToolInsteadException::KEY => 'Este membro provisório não pode ser convidado; use a ferramenta de mesclagem',
        InvitationForTheEmailAlreadyExistsApiException::KEY => 'Este e-mail já foi convidado para a organização. Aguarde o usuário aceitar o convite ou reenvie o e-mail do convite.',
        OverlappingTimeEntryApiException::KEY => 'Apontamentos sobrepostos não são permitidos.',
        UserResendEmailVerificationNoPendingEmailApiException::KEY => 'Não é possível reenviar o e-mail: não há e-mail pendente de verificação.',
    ],
    'unknown_error_in_admin_panel' => 'Ocorreu um erro desconhecido. Verifique os logs.',
];
