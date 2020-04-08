# magento2-extended-product-repository-ee

This module contains Magento EE specific functionality for the existing 
https://github.com/snowio/magento2-extended-product-repository module. 

## Special prices

This module allows for special prices to be saved as part of the standard snow product save request.
Special prices can be added to product payloads under extension attributes.

**Important** 

`store_id` is a required parameter for this request. This parameter can either be the store ID and integer OR the store code 
as this is usually more accessible. Example payload in extension_attributes below:

```
            "extension_attributes": {
                "special_price": [
                    {
                        "store_id": "admin",
                        "price": "10.84",
                        "sku": "13H4442KIT",
                        "price_from": "2017-12-03 13:48:06",
                        "price_to": "2017-12-04 00:00:00"
                    }
                ]
            }
```

A successful requests will create the product special prices in accordance to Magento EE guidelines. 
This means the special prices will be created as a scheduled change (more info found in Magento docs).

As a side note, scheduled changes will create multiple versions of a single product. These versions are then active between 
the dates specified.    