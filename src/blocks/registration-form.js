import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

registerBlockType('multiposter/registration-form', {
    title: __('Registration Form', 'jobit-vacancies-for-multiposter'),
    icon: 'id',
    category: 'widgets',
    edit: function Edit() {
        const blockProps = useBlockProps();
        return (
            <div {...blockProps}>
                <div style={{ padding: '20px', background: '#f0f0f0', textAlign: 'center' }}>
                    <strong>{__('Registration Form', 'jobit-vacancies-for-multiposter')}</strong>
                    <p>{__('Displays a registration form (no vacancy required).', 'jobit-vacancies-for-multiposter')}</p>
                </div>
            </div>
        );
    },
    save: () => null,
});
