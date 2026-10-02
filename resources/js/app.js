// Alpine.js is provided by Livewire 3, which bundles it. Importing and
// starting a second copy here would trigger Livewire's multiple-instance
// warning and initialise x-data twice.

document.addEventListener('alpine:init', () => {
    let activeLocks = 0;
    let previousBodyOverflow = '';

    const lockBody = () => {
        if (activeLocks === 0) {
            previousBodyOverflow = document.body.style.overflow;
            document.body.style.overflow = 'hidden';
        }

        activeLocks += 1;
    };

    const unlockBody = () => {
        activeLocks = Math.max(0, activeLocks - 1);

        if (activeLocks === 0) {
            document.body.style.overflow = previousBodyOverflow;
        }
    };

    window.Alpine.directive('scroll-lock', (element, { expression }, { cleanup, effect, evaluateLater }) => {
        let isLocked = false;

        const updateLock = (shouldLock) => {
            if (shouldLock && ! isLocked) {
                lockBody();
                isLocked = true;
            } else if (! shouldLock && isLocked) {
                unlockBody();
                isLocked = false;
            }
        };

        if (expression) {
            const evaluate = evaluateLater(expression);

            effect(() => evaluate((value) => updateLock(Boolean(value))));
        } else {
            updateLock(true);
        }

        cleanup(() => updateLock(false));
    });
});
