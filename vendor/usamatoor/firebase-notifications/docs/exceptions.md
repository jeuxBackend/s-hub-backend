# Exceptions

The package includes 3 custom exceptions:

| Exception                     | When It’s Thrown                                 |
|------------------------------|--------------------------------------------------|
| `MissingConfigurationException` | Config values are missing in `.env` or `config/` |
| `TokenRequestException`         | OAuth2 token fetch fails                         |
| `NotificationSendException`     | Notification cURL request fails                  |
