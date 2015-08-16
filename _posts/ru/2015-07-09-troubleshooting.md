---
layout: page
title: "Решение проблем"
category: ru/troubleshooting
lang: ru
order: 1
---

# Решение проблем

Есть проблемы с Gitify? Пожалуйста, прочитайте все пункты этой документации и поищите в [ошибках на GitHub](https://github.com/modmore/Gitify/issues). Если вы не нашли ответ, вы можете [создать issue](https://github.com/modmore/Gitify/issues/new) и описать проблему.

## MIGX

Если вы используете конфиги MIGX и файлы Gitify для изменения конфигов после каждого вызова команда `build` и `extract`, вы должны обратить внимание на порядок MIGX объектов в вашем файле `.gitify`. `migxConfig` должен быть последним из объектов MIGX. Более подробно все описано в [issue #90](https://github.com/modmore/Gitify/issues/90).
