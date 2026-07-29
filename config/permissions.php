<?php

/**
 * Registro canônico de permissões da aplicação.
 *
 * Convenção de nomes: domain.resource.action
 * Fonte única da verdade — seeders, testes e guards devem derivar deste arquivo.
 *
 * user_types indica quais tipos de usuário podem receber a permissão.
 * Developer recebe todas; os demais refletem a matriz em docs/specs/00-architecture/rbac.md.
 */

return [

    // ─────────────────────────────────────────────
    // Core — Usuários
    // ─────────────────────────────────────────────

    'core.users.list' => [
        'label' => 'Listar usuários',
        'user_types' => ['developer', 'admin'],
    ],

    'core.users.create' => [
        'label' => 'Criar usuário',
        'user_types' => ['developer', 'admin'],
    ],

    'core.users.view' => [
        'label' => 'Visualizar usuário',
        'user_types' => ['developer', 'admin', 'instructor'],
    ],

    'core.users.update' => [
        'label' => 'Atualizar usuário',
        'user_types' => ['developer', 'admin'],
    ],

    'core.users.delete' => [
        'label' => 'Excluir usuário',
        'user_types' => ['developer', 'admin'],
    ],

    'core.users.update-self' => [
        'label' => 'Atualizar próprio perfil',
        'user_types' => ['developer', 'admin', 'instructor', 'student'],
    ],

    'core.users.update-password' => [
        'label' => 'Alterar própria senha',
        'user_types' => ['developer', 'admin', 'instructor', 'student'],
    ],

    // ─────────────────────────────────────────────
    // Core — Convites
    // ─────────────────────────────────────────────

    'core.invitations.create' => [
        'label' => 'Convidar membro do tenant',
        'user_types' => ['developer', 'admin'],
    ],

    // ─────────────────────────────────────────────
    // Learning — Categorias
    // ─────────────────────────────────────────────

    'learning.categories.list' => [
        'label' => 'Listar categorias',
        'user_types' => ['developer', 'admin', 'instructor', 'student'],
    ],

    'learning.categories.create' => [
        'label' => 'Criar categoria',
        'user_types' => ['developer', 'admin', 'instructor'],
    ],

    'learning.categories.view' => [
        'label' => 'Visualizar categoria',
        'user_types' => ['developer', 'admin', 'instructor'],
    ],

    'learning.categories.update' => [
        'label' => 'Atualizar categoria',
        'user_types' => ['developer', 'admin', 'instructor'],
    ],

    'learning.categories.delete' => [
        'label' => 'Excluir categoria',
        'user_types' => ['developer', 'admin', 'instructor'],
    ],

    'learning.categories.system.manage' => [
        'label' => 'Gerenciar categorias do sistema',
        'user_types' => ['developer'],
    ],

    // ─────────────────────────────────────────────
    // Learning — Cursos
    // ─────────────────────────────────────────────

    'learning.courses.list' => [
        'label' => 'Listar cursos',
        'user_types' => ['developer', 'admin', 'instructor', 'student'],
    ],

    'learning.courses.create' => [
        'label' => 'Criar curso',
        'user_types' => ['developer', 'admin', 'instructor'],
    ],

    'learning.courses.view' => [
        'label' => 'Visualizar curso',
        'user_types' => ['developer', 'admin', 'instructor', 'student'],
    ],

    'learning.courses.update' => [
        'label' => 'Atualizar curso',
        'user_types' => ['developer', 'admin', 'instructor'],
    ],

    'learning.courses.delete' => [
        'label' => 'Excluir curso',
        'user_types' => ['developer', 'admin', 'instructor'],
    ],

    'learning.courses.publish' => [
        'label' => 'Publicar curso',
        'user_types' => ['developer', 'admin', 'instructor'],
    ],

    // ─────────────────────────────────────────────
    // Learning — Módulos
    // ─────────────────────────────────────────────

    'learning.modules.list' => [
        'label' => 'Listar módulos',
        'user_types' => ['developer', 'admin', 'instructor'],
    ],

    'learning.modules.create' => [
        'label' => 'Criar módulo',
        'user_types' => ['developer', 'admin', 'instructor'],
    ],

    'learning.modules.view' => [
        'label' => 'Visualizar módulo',
        'user_types' => ['developer', 'admin', 'instructor'],
    ],

    'learning.modules.update' => [
        'label' => 'Atualizar módulo',
        'user_types' => ['developer', 'admin', 'instructor'],
    ],

    'learning.modules.delete' => [
        'label' => 'Excluir módulo',
        'user_types' => ['developer', 'admin', 'instructor'],
    ],

    'learning.modules.reorder' => [
        'label' => 'Reordenar módulos',
        'user_types' => ['developer', 'admin', 'instructor'],
    ],

    // ─────────────────────────────────────────────
    // Learning — Aulas
    // ─────────────────────────────────────────────

    'learning.lessons.list' => [
        'label' => 'Listar aulas',
        'user_types' => ['developer', 'admin', 'instructor'],
    ],

    'learning.lessons.create' => [
        'label' => 'Criar aula',
        'user_types' => ['developer', 'admin', 'instructor'],
    ],

    'learning.lessons.view' => [
        'label' => 'Visualizar aula',
        'user_types' => ['developer', 'admin', 'instructor', 'student'],
    ],

    'learning.lessons.update' => [
        'label' => 'Atualizar aula',
        'user_types' => ['developer', 'admin', 'instructor'],
    ],

    'learning.lessons.delete' => [
        'label' => 'Excluir aula',
        'user_types' => ['developer', 'admin', 'instructor'],
    ],

    'learning.lessons.reorder' => [
        'label' => 'Reordenar aulas',
        'user_types' => ['developer', 'admin', 'instructor'],
    ],

    // ─────────────────────────────────────────────
    // Learning — Matrículas
    // ─────────────────────────────────────────────

    'learning.enrollments.list' => [
        'label' => 'Listar matrículas',
        'user_types' => ['developer', 'admin', 'instructor'],
    ],

    'learning.enrollments.create' => [
        'label' => 'Criar matrícula',
        'user_types' => ['developer', 'admin', 'instructor'],
    ],

    'learning.enrollments.view' => [
        'label' => 'Visualizar matrícula',
        'user_types' => ['developer', 'admin', 'instructor', 'student'],
    ],

    'learning.enrollments.update' => [
        'label' => 'Atualizar matrícula',
        'user_types' => ['developer', 'admin'],
    ],

    'learning.enrollments.delete' => [
        'label' => 'Excluir matrícula',
        'user_types' => ['developer', 'admin'],
    ],

    // ─────────────────────────────────────────────
    // Learning — Progresso
    // ─────────────────────────────────────────────

    'learning.progress.view' => [
        'label' => 'Visualizar progresso',
        'user_types' => ['developer', 'admin', 'instructor'],
    ],

    'learning.progress.update' => [
        'label' => 'Registrar progresso de aula',
        'user_types' => ['developer', 'student'],
    ],

    // ─────────────────────────────────────────────
    // Assessment — Questionários
    // ─────────────────────────────────────────────

    'assessment.questionnaires.list' => [
        'label' => 'Listar questionários',
        'user_types' => ['developer', 'admin', 'instructor'],
    ],

    'assessment.questionnaires.create' => [
        'label' => 'Criar questionário',
        'user_types' => ['developer', 'admin', 'instructor'],
    ],

    'assessment.questionnaires.view' => [
        'label' => 'Visualizar questionário',
        'user_types' => ['developer', 'admin', 'instructor'],
    ],

    'assessment.questionnaires.update' => [
        'label' => 'Atualizar questionário',
        'user_types' => ['developer', 'admin', 'instructor'],
    ],

    'assessment.questionnaires.delete' => [
        'label' => 'Excluir questionário',
        'user_types' => ['developer', 'admin', 'instructor'],
    ],

    // ─────────────────────────────────────────────
    // Assessment — Questões
    // ─────────────────────────────────────────────

    'assessment.questions.list' => [
        'label' => 'Listar questões',
        'user_types' => ['developer', 'admin', 'instructor'],
    ],

    'assessment.questions.create' => [
        'label' => 'Criar questão',
        'user_types' => ['developer', 'admin', 'instructor'],
    ],

    'assessment.questions.view' => [
        'label' => 'Visualizar questão',
        'user_types' => ['developer', 'admin', 'instructor'],
    ],

    'assessment.questions.update' => [
        'label' => 'Atualizar questão',
        'user_types' => ['developer', 'admin', 'instructor'],
    ],

    'assessment.questions.delete' => [
        'label' => 'Excluir questão',
        'user_types' => ['developer', 'admin', 'instructor'],
    ],

    // ─────────────────────────────────────────────
    // Assessment — Tentativas
    // ─────────────────────────────────────────────

    'assessment.attempts.list' => [
        'label' => 'Listar tentativas',
        'user_types' => ['developer', 'admin', 'instructor'],
    ],

    'assessment.attempts.view' => [
        'label' => 'Visualizar tentativa',
        'user_types' => ['developer', 'admin', 'instructor', 'student'],
    ],

    'assessment.attempts.create' => [
        'label' => 'Iniciar tentativa',
        'user_types' => ['developer', 'student'],
    ],

    'assessment.attempts.answer' => [
        'label' => 'Responder tentativa',
        'user_types' => ['developer', 'student'],
    ],

    'assessment.attempts.finish' => [
        'label' => 'Finalizar tentativa',
        'user_types' => ['developer', 'student'],
    ],

    // ─────────────────────────────────────────────
    // Assessment — Certificados
    // ─────────────────────────────────────────────

    'assessment.certificates.list' => [
        'label' => 'Listar certificados',
        'user_types' => ['developer', 'admin', 'instructor', 'student'],
    ],

    'assessment.certificates.view' => [
        'label' => 'Visualizar certificado',
        'user_types' => ['developer', 'admin', 'instructor', 'student'],
    ],

    'assessment.certificates.revoke' => [
        'label' => 'Revogar certificado',
        'user_types' => ['developer', 'admin'],
    ],

    // ─────────────────────────────────────────────
    // Financial — Gateways de pagamento
    // ─────────────────────────────────────────────

    'financial.payment-gateways.list' => [
        'label' => 'Listar gateways de pagamento',
        'user_types' => ['developer', 'admin'],
    ],

    'financial.payment-gateways.update' => [
        'label' => 'Configurar gateways de pagamento',
        'user_types' => ['developer', 'admin'],
    ],

    'financial.orders.confirm-manual-payment' => [
        'label' => 'Confirmar pagamento manual de pedidos',
        'user_types' => ['developer', 'admin'],
    ],

    'financial.checkout.create' => [
        'label' => 'Criar checkout de curso',
        'user_types' => ['developer', 'student'],
    ],

];
