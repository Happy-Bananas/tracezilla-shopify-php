<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Output;

final class LocationTableRenderer
{
    /** @param array{count:int,locations:list<array>} $result */
    public function render(array $result): string
    {
        $output = sprintf("%-24s %-9s %-10s %-13s %-22s %s\n", 'Name', 'Status', 'Inventory', 'Online orders', 'Legacy ID', 'GraphQL ID');
        $output .= str_repeat('-', 112)."\n";
        foreach ($result['locations'] as $location) {
            $output .= sprintf(
                "%-24s %-9s %-10s %-13s %-22s %s\n",
                $location['name'],
                $location['is_active'] ? 'Active' : 'Inactive',
                $location['has_active_inventory'] ? 'Yes' : 'No',
                $location['fulfills_online_orders'] ? 'Yes' : 'No',
                $location['legacy_id'],
                $location['graph_ql_id'],
            );
            $address = array_filter([
                $location['address']['address1'], $location['address']['address2'],
                trim(implode(' ', array_filter([$location['address']['zip'], $location['address']['city']]))),
                $location['address']['province'], $location['address']['country'],
            ]);
            $output .= 'Address: '.($address === [] ? '—' : implode(', ', $address))."\n";
        }

        return $output.sprintf("\n%d location(s) returned.\n", $result['count']);
    }
}
