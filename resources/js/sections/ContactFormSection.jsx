const FIELDS = [
    { name: 'name', label: 'Your name', type: 'text', autoComplete: 'name' },
    { name: 'email', label: 'Email address', type: 'email', autoComplete: 'email' },
    { name: 'phone', label: 'Phone number', type: 'tel', autoComplete: 'tel' },
    { name: 'suburb', label: 'Suburb', type: 'text', autoComplete: 'address-level2' },
];

export default function ContactFormSection({ data, anchor }) {
    return (
        <section className="contact-form" id={anchor}>
            <div className="container contact-form__grid">
                <div className="contact-form__copy">
                    {data.eyebrow ? <div className="eyebrow-line">{data.eyebrow}</div> : null}

                    {data.heading || data.headingEm ? (
                        <h2>
                            {data.heading} {data.headingEm ? <em>{data.headingEm}</em> : null}
                        </h2>
                    ) : null}

                    {data.intro ? <p className="section-lead">{data.intro}</p> : null}
                </div>

                <form className="contact-form__form" method="post">
                    {FIELDS.map((f) => (
                        <label className="contact-form__field" key={f.name}>
                            <span>{f.label}</span>
                            <input type={f.type} name={f.name} autoComplete={f.autoComplete} />
                        </label>
                    ))}

                    <label className="contact-form__field">
                        <span>How can we help?</span>
                        <textarea name="message" rows="4" />
                    </label>

                    {data.consent ? (
                        <label className="contact-form__consent">
                            <input type="checkbox" name="consent" />
                            <span>{data.consent}</span>
                        </label>
                    ) : null}

                    <button type="submit" className="btn primary">
                        {data.submitLabel || 'Send enquiry'}
                    </button>
                </form>
            </div>
        </section>
    );
}
