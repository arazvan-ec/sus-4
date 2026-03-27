# Project Context: enBandeja-service

## Que es
Microservicio PHP/Symfony que gestiona suscripciones de usuarios a entidades editoriales y orquesta notificaciones por email cuando se publica contenido relevante.

## Stack
- PHP 8.4+, Symfony 7.2
- Doctrine ORM (PostgreSQL)
- Symfony Messenger + RabbitMQ
- Mailchimp Marketing API (audiencias)
- Mailchimp Transactional via notifier-service (envio)

## Arquitectura
- Tres capas: Domain / Application / Infrastructure
- Domain puro, sin dependencias de framework
- REST API con JWT auth para suscripciones
- Event-driven para editorial.published via RabbitMQ
- Soft delete en suscripciones (active/inactive)

## Componentes clave
1. **Subscription API** — CRUD REST para seguir/dejar de seguir entidades
2. **EditorialHandler** — Consume eventos editorial.published, verifica flags, crea campanas
3. **CampaignProcessor** — Worker que resuelve audiencias y despacha a notifier
4. **MailchimpClient** — Sync async de audiencias con Mailchimp Marketing API

## Servicios externos
- journalist-svc, tag-svc, section-svc (verificar flags)
- notifier-service (envio de emails via Mailchimp Transactional)
- Legacy/Jarvis CMS (fuente de eventos editorial.published)
