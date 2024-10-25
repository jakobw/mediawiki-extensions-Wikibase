# @wmde/wikibase-rest-api

This is an auto-generated Wikibase REST API client library using the API’s [OpenAPI document](https://doc.wikimedia.org/Wikibase/master/js/rest-api/openapi.json). It is generated via [`OpenAPITools/openapi-generator`](https://github.com/OpenAPITools/openapi-generator).

```js
import { ApiClient, LabelsApi } from '@wmde/wikibase-rest-api';

const apiClient = new ApiClient( 'https://www.wikidata.org/w/rest.php//wikibase/v0' );
apiClient.defaultHeaders[ 'User-Agent' ] = '[custom user Agent]';

console.log( await new LabelsApi( apiClient ).getItemLabel( 'Q42', 'en' ) );
```

Below you can find the automatically generated documentation:
