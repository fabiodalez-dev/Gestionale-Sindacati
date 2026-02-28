-- Aggiorna ENUM metodo_pagamento per includere 'sepa'
ALTER TABLE iscrizioni MODIFY metodo_pagamento ENUM('trattenuta in busta paga','rinnovo annuale','sepa') NOT NULL;
