<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Dashboard\Widgets;

use Illuminate\Contracts\Auth\Authenticatable;
use InvalidArgumentException;
use SeviaFlow\Ui\Dashboard\Dashboard;

final class DashboardWidgetResolver
{
    /**
     * @param  class-string<Dashboard>  $dashboardClass
     * @return array<int, Widget>
     */
    public function resolve(string $dashboardClass, ?Authenticatable $user = null): array
    {
        $rawWidgets = $dashboardClass::widgets();
        $widgets = [];
        $seen = [];

        foreach ($rawWidgets as $widget) {
            if (! $widget instanceof Widget) {
                throw new InvalidArgumentException(sprintf(
                    'Dashboard [%s] widgets() must return only %s instances.',
                    $dashboardClass,
                    Widget::class
                ));
            }

            $name = trim($widget->name());
            if ($name === '') {
                throw new InvalidArgumentException(sprintf(
                    'Dashboard [%s] contains widget with empty name.',
                    $dashboardClass
                ));
            }

            if (isset($seen[$name])) {
                throw new InvalidArgumentException(sprintf(
                    'Dashboard [%s] contains duplicate widget name [%s].',
                    $dashboardClass,
                    $name
                ));
            }

            $seen[$name] = true;

            if (! $widget->isVisible($user)) {
                continue;
            }

            $widgets[] = $widget;
        }

        return $widgets;
    }
}
