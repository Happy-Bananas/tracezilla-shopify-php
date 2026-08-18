<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Shopify\Queries;

final class GetOrders
{
    public const QUERY = <<<'GRAPHQL'
        query GetOrders($first: Int!, $after: String, $query: String!) {
          orders(first: $first, after: $after, query: $query, sortKey: CREATED_AT, reverse: true) {
            nodes {
              id
              legacyResourceId
              name
              createdAt
              cancelledAt
              email
              phone
              note
              poNumber
              currencyCode
              shippingAddress {
                name
                company
                address1
                address2
                zip
                city
                province
                provinceCode
                countryCodeV2
                phone
              }
              lineItems(first: 250) {
                nodes {
                  sku
                  currentQuantity
                  discountedUnitPriceAfterAllDiscountsSet {
                    shopMoney {
                      amount
                      currencyCode
                    }
                  }
                }
                pageInfo {
                  hasNextPage
                }
              }
            }
            pageInfo {
              hasNextPage
              endCursor
            }
          }
        }
        GRAPHQL;
}
