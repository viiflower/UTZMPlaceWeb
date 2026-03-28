import { useEffect } from 'react'
import { Navigate, useParams } from 'react-router-dom'

const INFO_PAGES = {
  metodos_pago: {
    title: 'Métodos de pago',
    browserTitle: 'Métodos de pago | Utzmplace',
    tag: 'Pagos y cobros',
    intro:
      'Integramos transferencias bancarias y soluciones digitales verificadas para mantener cada operación dentro de la plataforma.',
    sections: [
      {
        title: 'Lo esencial',
        paragraphs: [
          'Actualmente aceptamos pagos mediante transferencia bancaria y sistemas de pago digitales confiables.',
          'Todos los pagos se procesan dentro de la plataforma para mayor seguridad entre comprador y vendedor.',
        ],
        note:
          'Confirma con tu contraparte antes de finalizar y revisa el estado del pago desde tu panel.',
      },
      {
        title: 'Recomendaciones rápidas',
        list: [
          'Guarda el comprobante de pago hasta cerrar el pedido.',
          'Verifica que el nombre del beneficiario coincida con el vendedor registrado.',
          'Reporta cualquier irregularidad desde el centro de ayuda para detener el desembolso.',
        ],
      },
    ],
  },
  seguridad: {
    title: 'Seguridad',
    browserTitle: 'Seguridad de la cuenta | Utzmplace',
    tag: 'Confianza',
    intro:
      'Protegemos cada transacción con procesos internos y monitoreo constante.',
    sections: [
      {
        title: 'Lo esencial',
        paragraphs: [
          'Nos esforzamos por mantener la seguridad de cada transacción.',
          'La información personal y financiera de los usuarios no se comparte con terceros y se almacena de forma protegida.',
        ],
        note:
          'Actualiza tu contraseña con frecuencia y evita compartir datos sensibles por mensajes externos.',
      },
      {
        title: 'Buenas prácticas',
        list: [
          'Verifica que el enlace inicie con https y corresponda a Utzmplace.',
          'Utiliza dispositivos confiables cuando gestiones pagos o datos personales.',
          'Reporta cualquier actividad sospechosa para que podamos bloquear la cuenta involucrada.',
        ],
      },
    ],
  },
  facturacion: {
    title: 'Facturación',
    browserTitle: 'Facturación y comprobantes | Utzmplace',
    tag: 'Registros internos',
    intro:
      'Consulta los comprobantes generados por cada compra sin salir de tu perfil.',
    sections: [
      {
        title: 'Lo esencial',
        paragraphs: [
          'Cada compra genera un comprobante interno que puedes consultar desde tu perfil.',
          'Por ser un proyecto universitario, no emitimos facturas oficiales, pero mantenemos registro de todas las operaciones realizadas.',
        ],
        note:
          'Descarga y guarda tus comprobantes internos si necesitas respaldar una transacción.',
      },
      {
        title: 'Organiza tus registros',
        list: [
          'Revisa la sección de pedidos para localizar los comprobantes recientes.',
          'Si detectas datos incorrectos, solicita una corrección desde soporte.',
          'Comparte tus comprobantes solo con personas de confianza.',
        ],
      },
    ],
  },
  envios: {
    title: 'Envíos',
    browserTitle: 'Envíos y entregas | Utzmplace',
    tag: 'Entregas',
    intro:
      'Coordina entregas seguras y puntuales directamente con la contraparte.',
    sections: [
      {
        title: 'Lo esencial',
        paragraphs: [
          'El envío o entrega de los productos se coordina directamente entre comprador y vendedor.',
          'Sugerimos acordar un punto de entrega seguro dentro o cerca de la universidad.',
        ],
        note:
          'Llega con anticipación y confirma la identidad de la persona antes de entregar el producto.',
      },
      {
        title: 'Consejos para entregas',
        list: [
          'Define fecha y hora con suficiente anticipación.',
          'Prefiere zonas iluminadas y con vigilancia.',
          'Lleva únicamente el producto pactado para evitar confusiones.',
        ],
      },
    ],
  },
  devoluciones: {
    title: 'Devoluciones',
    browserTitle: 'Devoluciones | Utzmplace',
    tag: 'Soporte postventa',
    intro:
      'Facilitamos la comunicación para que compradores y vendedores resuelvan cualquier inconveniente.',
    sections: [
      {
        title: 'Lo esencial',
        paragraphs: [
          'Las devoluciones dependen del acuerdo entre ambas partes.',
          'Nuestro sistema permite notificar problemas con un pedido para que el equipo revise el caso y ayude a gestionar una solución.',
        ],
        note:
          'Mientras el caso está en revisión, evita enviar nuevos pagos al mismo vendedor.',
      },
      {
        title: 'Cómo proceder',
        list: [
          'Documenta con fotos o videos el estado del producto.',
          'Describe el motivo de la devolución al abrir el ticket.',
          'Mantente atento a las respuestas del equipo para completar el proceso.',
        ],
      },
    ],
  },
  reembolsos: {
    title: 'Reembolsos',
    browserTitle: 'Reembolsos | Utzmplace',
    tag: 'Protección a compradores',
    intro:
      'Retenemos el pago hasta confirmar que todo se entregó según lo acordado.',
    sections: [
      {
        title: 'Lo esencial',
        paragraphs: [
          'El pago se mantiene retenido en la plataforma hasta que el comprador confirme la recepción del producto.',
          'En caso de no concretarse la entrega o existir algún inconveniente comprobable, el reembolso se procesará de forma prioritaria y lo antes posible.',
        ],
        note:
          'Brinda evidencia clara para acelerar la evaluación del reembolso.',
      },
      {
        title: 'Flujo de reembolso',
        list: [
          'Confirma tu pedido solo cuando recibas el producto en buen estado.',
          'Abre un reporte si detectas retrasos o incongruencias.',
          'Sigue el hilo del caso desde tu bandeja de mensajes.',
        ],
      },
    ],
  },
  sobre_nosotros: {
    title: 'Sobre nosotros',
    browserTitle: 'Sobre Utzmplace',
    tag: 'Nuestro proyecto',
    intro:
      'Construimos un espacio confiable hecho por y para la comunidad universitaria.',
    sections: [
      {
        title: 'Lo esencial',
        paragraphs: [
          'Somos un grupo de estudiantes que desarrolla un proyecto universitario con el fin de crear un espacio seguro y funcional donde miembros de la comunidad puedan comprar, vender o intercambiar productos y servicios de manera sencilla y confiable.',
        ],
        note:
          'Cada mejora nace de los comentarios que recibimos; comparte tus ideas.',
      },
      {
        title: 'Nuestro compromiso',
        list: [
          'Impulsar economías locales dentro del campus.',
          'Promover transacciones transparentes.',
          'Fomentar la colaboración entre generaciones de estudiantes.',
        ],
      },
    ],
  },
  centro_de_ayuda: {
    title: 'Centro de ayuda',
    browserTitle: 'Centro de ayuda | Utzmplace',
    tag: 'Soporte',
    intro:
      'Unificamos respuestas claras para que sigas comprando y vendiendo sin dudas.',
    sections: [
      {
        title: 'Lo esencial',
        paragraphs: [
          'Aquí encontrarás respuestas a las preguntas más comunes sobre compras, ventas, entregas y reembolsos.',
          'Nuestro objetivo es facilitar el uso de la plataforma para toda la comunidad universitaria.',
        ],
        note:
          'Si no ves tu duda, abre un ticket y te responderemos a la brevedad.',
      },
      {
        title: 'Temas frecuentes',
        list: [
          'Pasos para comprar y vender.',
          'Políticas de envíos y entregas.',
          'Procesos de devoluciones y reembolsos.',
        ],
      },
    ],
  },
  contacto: {
    title: 'Contacto',
    browserTitle: 'Contacto | Utzmplace',
    tag: 'Estamos para ayudarte',
    intro:
      'Nuestro equipo responde cada solicitud con seguimiento personalizado.',
    sections: [
      {
        title: 'Lo esencial',
        paragraphs: [
          'Si necesitas asistencia personalizada o reportar un problema, puedes contactarnos por medio del formulario de soporte o a través del correo oficial del proyecto.',
          'Procuramos responder en un plazo corto.',
        ],
        note:
          'Incluye capturas o referencias de pedido para acelerar la ayuda.',
      },
      {
        title: 'Canales disponibles',
        list: [
          'Formulario de soporte dentro de tu perfil.',
          'Correo oficial con seguimiento académico.',
          'Espacios de mentoría en el laboratorio de innovación.',
        ],
      },
    ],
  },
  terminos_privacidad: {
    title: 'Términos y privacidad',
    browserTitle: 'Términos y privacidad | Utzmplace',
    tag: 'Uso responsable',
    intro:
      'Cuidamos tus datos con lineamientos claros y transparentes.',
    sections: [
      {
        title: 'Lo esencial',
        paragraphs: [
          'Respetamos la privacidad de nuestros usuarios.',
          'Los datos recolectados se utilizan únicamente para el funcionamiento del sistema y no se comparten con terceros.',
          'Al usar la plataforma, aceptas nuestras políticas de uso y convivencia universitaria.',
        ],
        note:
          'Puedes solicitar la eliminación de tu cuenta y datos escribiendo a soporte.',
      },
      {
        title: 'Principios',
        list: [
          'Recolectamos solo la información necesaria para operar.',
          'Limitamos el acceso del equipo técnico a los datos sensibles.',
          'Actualizamos las políticas conforme evoluciona el proyecto.',
        ],
      },
    ],
  },
}

export default function InfoPage() {
  const { slug = '' } = useParams()
  const page = INFO_PAGES[slug]

  useEffect(() => {
    if (page?.browserTitle) {
      document.title = page.browserTitle
    }
  }, [page?.browserTitle])

  if (!page) {
    return <Navigate replace to="/" />
  }

  return (
    <main className="container info-page">
      <section className="info-hero">
        <span className="info-hero__tag">{page.tag}</span>
        <h1>{page.title}</h1>
        <p>{page.intro}</p>
      </section>

      <section className="info-grid">
        {page.sections.map((section) => (
          <article key={section.title} className="info-card">
            <h2>{section.title}</h2>
            {section.paragraphs?.map((paragraph) => (
              <p key={paragraph}>{paragraph}</p>
            ))}
            {section.note ? <div className="info-note">{section.note}</div> : null}
            {section.list?.length ? (
              <ul className="info-list">
                {section.list.map((item) => (
                  <li key={item}>{item}</li>
                ))}
              </ul>
            ) : null}
          </article>
        ))}
      </section>
    </main>
  )
}
