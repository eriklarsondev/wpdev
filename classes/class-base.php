<?php
namespace wpdev;

class Base
{
    /**
     * formats label and prepends custom prefix to label if applicable
     *
     * @param string $label
     * @param string $divider
     * @param boolean $prefix
     *
     * @return string
     */
    protected function formatLabel($label, $divider = '_', $prefix = true)
    {
        $formattedLabel = trim(strtolower($label));
        $formattedLabel = preg_replace('/\s+/', $divider, $formattedLabel);

        $isPrefixed = $formattedLabel === 'wpdev'
            || strpos($formattedLabel, 'wpdev' . $divider) === 0;

        if ($prefix && !$isPrefixed) {
            $formattedLabel = 'wpdev' . $divider . $formattedLabel;
        }

        return $formattedLabel;
    }
}
