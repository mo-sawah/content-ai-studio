import { useRef, useEffect, useCallback } from "@wordpress/element";
import { DropdownMenu } from "@wordpress/components";
import { chevronDown } from "@wordpress/icons";

function CustomDropdown({
  label,
  text,
  options,
  onChange,
  disabled,
  helpText,
}) {
  const dropdownRef = useRef(null);

  // Function to update the popover width
  const updatePopoverWidth = useCallback(() => {
    if (dropdownRef.current) {
      const width = dropdownRef.current.offsetWidth;

      // Set CSS custom property on the root element
      document.documentElement.style.setProperty(
        "--atm-popover-width",
        `${width}px`
      );

      // Also set it on the dropdown element for more specific targeting
      dropdownRef.current.style.setProperty(
        "--current-dropdown-width",
        `${width}px`
      );

      // Find and directly style any existing popovers
      const popovers = document.querySelectorAll(
        ".atm-popover .components-popover__content"
      );
      popovers.forEach((popover) => {
        popover.style.width = `${width}px`;
        popover.style.minWidth = `${width}px`;
      });
    }
  }, []);

  // Update width when component mounts and when it resizes
  useEffect(() => {
    // Initial measurement after a small delay to ensure DOM is ready
    const timer = setTimeout(() => {
      updatePopoverWidth();
    }, 100);

    // Set up ResizeObserver to watch for size changes
    const resizeObserver = new ResizeObserver(() => {
      updatePopoverWidth();
    });

    if (dropdownRef.current) {
      resizeObserver.observe(dropdownRef.current);
    }

    return () => {
      clearTimeout(timer);
      resizeObserver.disconnect();
    };
  }, [updatePopoverWidth]);

  // Update width when text changes (which might change button width)
  useEffect(() => {
    updatePopoverWidth();
  }, [text, updatePopoverWidth]);

  return (
    <div className="atm-dropdown-field" ref={dropdownRef}>
      <label className="atm-dropdown-label">{label}</label>
      <DropdownMenu
        className="atm-custom-dropdown"
        icon={chevronDown}
        text={text}
        controls={options.map((option) => ({
          title: option.label,
          onClick: () => onChange(option),
        }))}
        disabled={disabled}
        popoverProps={{
          className: "atm-popover",
          onMount: () => {
            // Update width when popover mounts
            setTimeout(updatePopoverWidth, 10);
          },
        }}
      />
      {helpText && <p className="atm-dropdown-help">{helpText}</p>}
    </div>
  );
}

export default CustomDropdown;
