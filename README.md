# Nook Social Login

WordPress-плагин для регистрации и входа через социальные сети:
**VK ID, Steam, Battle.net, Яндекс ID, Google, Twitch, Discord**.

Автор: Горшков Сергей Владимирович — [nookbat.ru](https://nookbat.ru/)

## Возможности

- Вход и автоматическая регистрация при первом входе (можно отключить).
- Кнопки на форме входа и регистрации WordPress, над формой комментариев, на формах WooCommerce и через шорткод.
- Привязка и отвязка соцсетей в профиле пользователя (`Пользователи → Профиль → Социальные сети`).
- Запрос e-mail, если соцсеть его не передаёт (Steam, Battle.net).
- Привязка к существующему аккаунту по подтверждённому e-mail; администраторы автоматически не привязываются.
- Аватар из соцсети.
- Защита: `state` + привязка к браузеру (cookie), PKCE (VK ID, Google), серверная проверка подписи Steam OpenID.
- Языки: русский, английский, китайский (упрощённый).

Требования: WordPress 6.3+, PHP 7.4+.

## Установка

1. Скачайте `nook-social-login-<версия>.zip` из [Releases](https://github.com/Serge-Nook/nook-social-login/releases).
2. `Плагины → Добавить новый → Загрузить плагин`, выберите архив, активируйте.
3. Откройте `Настройки → Вход через соцсети`.

## Настройка соцсетей

Для каждой сети включите её, создайте приложение в консоли разработчика, укажите
**Redirect URL** из карточки сети (кнопка «Копировать») и вставьте ключи.

| Сеть | Консоль | Что нужно |
|---|---|---|
| VK ID | [id.vk.ru/about/business](https://id.vk.ru/about/business/go) | App ID, базовый домен, доверенный Redirect URL, доступ к почте |
| Steam | — | Ничего; ключ [Steam Web API](https://steamcommunity.com/dev/apikey) необязателен |
| Battle.net | [develop.battle.net](https://develop.battle.net/access/clients) | Client ID, Client secret, регион |
| Яндекс ID | [oauth.yandex.ru](https://oauth.yandex.ru/client/new) | ClientID, Client secret, доступ к почте, имени и аватару |
| Google | [console.cloud.google.com](https://console.cloud.google.com/apis/credentials) | OAuth client ID типа «Веб-приложение» |
| Twitch | [dev.twitch.tv/console](https://dev.twitch.tv/console/apps) | Client ID, Client secret (тип клиента Confidential) |
| Discord | [discord.com/developers](https://discord.com/developers/applications) | Client ID, Client secret (раздел OAuth2) |

## Шорткод

```text
[nook_social_login]
[nook_social_login style="icons" providers="vk,google,discord" heading=""]
[nook_social_login link="1"]
```

| Атрибут | Значение |
|---|---|
| `providers` | Список через запятую: `vk, steam, battlenet, yandex, google, twitch, discord` |
| `style` | `full` (иконка и текст) или `icons` |
| `heading` | Заголовок над кнопками; пустая строка — без заголовка |
| `redirect` | Адрес после входа |
| `link` | `1` — вошедшим пользователям показываются кнопки привязки |

## Для разработчиков

- Фильтр `nooksl_endpoint( $url, $type, $provider_id )` — адреса OAuth/OpenID (`authorize`, `token`, `userinfo`, `verify`).
- Фильтр `nooksl_new_user_data( $userdata, $provider, $profile )` — данные нового пользователя.
- Фильтр `nooksl_allow_login( true, $user, $provider )` — запретить вход.
- Фильтр `nooksl_login_redirect( $url, $user, $flow )` — адрес после входа.
- Действия `nooksl_registered`, `nooksl_linked`, `nooksl_unlinked`, `nooksl_login`.

Проверка кода: `phpcs --standard=phpcs.xml.dist`. Сборка архива: `bin/build-zip.sh`.

## Лицензия

GPL-2.0-or-later
