import { useRef } from '@wordpress/element';
import { DropdownMenu } from '@wordpress/components';
import { chevronDown } from '@wordpress/icons';

function CustomDropdown({ label, text, options, onChange, disabled, helpText }) {
    const dropdownRef = useRef(null);

    return (
        <div className="atm-dropdown-field" ref={dropdownRef}>
            <label className="atm-dropdown-label">{label}</label>
            <DropdownMenu
                className="atm-custom-dropdown"
                icon={chevronDown}
                text={text}
                controls={options.map(option => ({
                    title: option.label,
                    onClick: () => onChange(option),
                }))}
                disabled={disabled}
                popoverProps={{
                    className: 'atm-popover',
                    style: {
                        '--atm-dropdown-width': dropdownRef.current?.offsetWidth
                            ? `${dropdownRef.current.offsetWidth}px`
                            : 'auto',
                    },
                }}
            />
            {helpText && <p className="atm-dropdown-help">{helpText}</p>}
        </div>
    );
}

export default CustomDropdown;